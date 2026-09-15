<?php

declare(strict_types=1);

namespace App\Modules\Licensing\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Models\RawAttendanceLog;
use App\Modules\Attendance\Services\AttendancePipelineService;
use App\Modules\CoreHR\Models\Branch;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\Licensing\Services\LicenseService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AttendanceSyncController extends Controller
{
    public function __construct(
        protected LicenseService $licenseService,
        protected AttendancePipelineService $pipelineService
    ) {}

    /**
     * Tiếp nhận batch log quẹt thẻ từ Golang Edge Collector Daemon có xác thực HMAC-SHA256
     */
    public function sync(Request $request): JsonResponse
    {
        $rawContent = $request->getContent();
        $signature = $request->header('X-SmartHR-Signature') ?? $request->input('signature');

        if (empty($signature)) {
            return response()->json([
                'error' => 'Thiếu chữ ký số X-SmartHR-Signature trong HTTP header.',
            ], 403);
        }

        // 1. Phân tích gói tin
        $payload = json_decode($rawContent, true);
        if (!is_array($payload)) {
            return response()->json([
                'error' => 'Định dạng gói tin JSON không hợp lệ.',
            ], 400);
        }

        $branchCode = $request->header('X-SmartHR-Branch') ?? ($payload['branch_code'] ?? null);
        $branch = $branchCode ? Branch::where('code', $branchCode)->first() : null;

        // 2. Xác thực HMAC-SHA256 với khóa bí mật trong smart-hr.lic hoặc branch secret_key
        $isHmacValid = $this->licenseService->verifySyncHmac($rawContent, (string)$signature);
        if (!$isHmacValid && $branch && !empty($branch->secret_key)) {
            $isHmacValid = $this->licenseService->verifySyncHmac($rawContent, (string)$signature, $branch->secret_key);
        }

        if (!$isHmacValid) {
            Log::warning('Cảnh báo bảo mật Edge Sync: Chữ ký HMAC không khớp với giấy phép hoặc chi nhánh.', [
                'ip' => $request->ip(),
                'signature' => $signature,
                'branch' => $branchCode,
            ]);

            return response()->json([
                'error' => 'Xác thực thất bại! Chữ ký HMAC không hợp lệ hoặc tệp giấy phép bản quyền không khớp.',
            ], 403);
        }
        $batchId = $payload['batch_id'] ?? ('BATCH_' . time());
        $punches = $payload['punches'] ?? [];

        if (empty($branchCode)) {
            return response()->json([
                'error' => 'Thiếu thông tin chi nhánh (branch_code).',
            ], 422);
        }

        // 3. Tìm chi nhánh và tenant tương ứng
        $branch = Branch::where('code', $branchCode)->first();
        if (!$branch) {
            return response()->json([
                'error' => "Không tìm thấy chi nhánh với mã [{$branchCode}] trong hệ thống.",
            ], 404);
        }

        $tenantId = $branch->tenant_id;
        $savedCount = 0;
        $recalculateTargets = [];

        // 4. Lưu từng lượt quẹt thẻ vào bảng raw_attendance_logs
        foreach ($punches as $punch) {
            $userCode = $punch['user_code'] ?? ($punch['device_user_code'] ?? null);
            $punchTime = $punch['punch_time'] ?? null;

            if (empty($userCode) || empty($punchTime)) {
                continue;
            }

            // Tìm nhân viên theo user_code hoặc attendance_code trong tenant
            $employee = Employee::where('tenant_id', $tenantId)
                ->where(function ($query) use ($userCode) {
                    $query->where('user_code', $userCode)
                          ->orWhere('attendance_code', $userCode);
                })
                ->first();

            if (!$employee) {
                // Không bỏ qua log thô nếu không tìm thấy nhân viên (vẫn có thể lưu để đối soát nếu cần)
                continue;
            }

            $parsedPunchTime = Carbon::parse($punchTime);
            $rawHash = hash('sha256', json_encode($punch, JSON_UNESCAPED_UNICODE));

            // Đánh dấu đối tượng cần tính lại công (ngay cả khi log đã tồn tại để cập nhật In/Out mới)
            $workDateStr = $parsedPunchTime->format('Y-m-d');
            $targetKey = "{$employee->id}_{$workDateStr}";
            if (!isset($recalculateTargets[$targetKey])) {
                $recalculateTargets[$targetKey] = [
                    'employee_id' => (int) $employee->id,
                    'work_date' => $parsedPunchTime->copy()->startOfDay(),
                ];
            }

            // Kiểm tra trùng lặp log quẹt thẻ theo employee_id và punch_time
            $exists = RawAttendanceLog::where('employee_id', $employee->id)
                ->where('punch_time', $parsedPunchTime)
                ->exists();

            if (!$exists) {
                $punchState = strtoupper($punch['punch_state'] ?? 'AUTO');
                if (!in_array($punchState, ['CHECK_IN', 'CHECK_OUT', 'BREAK_OUT', 'BREAK_IN', 'AUTO'])) {
                    $punchState = 'AUTO';
                }

                $verifyMode = strtoupper($punch['verification_mode'] ?? 'FINGERPRINT');

                RawAttendanceLog::create([
                    'tenant_id' => $tenantId,
                    'branch_id' => $branch->id,
                    'employee_id' => $employee->id,
                    'punch_time' => $parsedPunchTime,
                    'punch_state' => $punchState,
                    'device_sn' => $punch['device_sn'] ?? 'GO-EDGE-COLLECTOR',
                    'verification_mode' => $verifyMode,
                    'raw_payload_hash' => $rawHash,
                    'sync_batch_id' => $batchId,
                ]);

                $savedCount++;
            }
        }

        // 5. Tự động kích hoạt 7-Pipe Pipeline để tính toán ngay lập tức công ngày
        $calculatedCount = 0;
        foreach ($recalculateTargets as $target) {
            try {
                $this->pipelineService->calculateDate(
                    $tenantId,
                    $target['employee_id'],
                    $target['work_date']
                );
                $calculatedCount++;
            } catch (\Throwable $e) {
                Log::error('Lỗi tính công tự động sau khi sync log:', [
                    'tenant_id' => $tenantId,
                    'employee_id' => $target['employee_id'],
                    'work_date' => $target['work_date']->format('Y-m-d'),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => "Đã tiếp nhận {$savedCount} bản ghi quẹt thẻ mới và tự động tính công cho {$calculatedCount} nhân sự/ngày.",
            'batch_id' => $batchId,
            'processed_records' => $savedCount,
            'calculated_days' => $calculatedCount,
            'total_received' => count($punches),
            'timestamp' => now()->toIso8601String(),
        ], 200);
    }

    /**
     * Cung cấp danh sách nhân sự đang làm việc tại chi nhánh cho Edge Collector / Simulator
     */
    public function employees(Request $request): JsonResponse
    {
        $branchCode = $request->header('X-SmartHR-Branch') ?? $request->query('branch_code', 'HN_HQ');
        $branch = Branch::where('code', $branchCode)->first();
        if (!$branch) {
            return response()->json([
                'error' => "Không tìm thấy chi nhánh với mã [{$branchCode}].",
            ], 404);
        }

        $employees = Employee::where('tenant_id', $branch->tenant_id)
            ->where('status', '!=', 'TERMINATED')
            ->whereHas('currentTimeline.department', function ($q) use ($branch) {
                $q->where('branch_id', $branch->id);
            })
            ->get(['id', 'user_code', 'attendance_code', 'full_name']);

        if ($employees->isEmpty()) {
            $employees = Employee::where('tenant_id', $branch->tenant_id)
                ->where('status', '!=', 'TERMINATED')
                ->get(['id', 'user_code', 'attendance_code', 'full_name']);
        }

        return response()->json([
            'status' => 'success',
            'branch_code' => $branchCode,
            'branch_name' => $branch->name,
            'total' => $employees->count(),
            'employees' => $employees->map(fn ($emp) => [
                'id' => $emp->id,
                'user_code' => $emp->user_code,
                'attendance_code' => $emp->attendance_code,
                'full_name' => $emp->full_name,
            ]),
        ]);
    }
}
