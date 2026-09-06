<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Models\MonthlyAttendance;
use App\Modules\Compliance\Services\AuditTrailService;
use App\Modules\Compliance\Services\TemporalLockService;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\Payroll\Models\PayrollPeriod;
use App\Modules\Payroll\Models\PayrollRecord;
use App\Modules\Payroll\Services\PayrollCalculationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayrollController extends Controller
{
    public function __construct(
        protected PayrollCalculationService $payrollCalculationService,
        protected TemporalLockService $temporalLockService,
        protected AuditTrailService $auditTrailService
    ) {}

    /**
     * Màn hình Bảng lương doanh nghiệp & Điều hành Chu trình tính lương
     */
    public function index(Request $request): Response
    {
        $tenantId = (int) ($request->user()->tenant_id ?? 1);
        $monthYear = $request->query('month_year', Carbon::now()->format('Y-m'));

        // 1. Lấy thông tin PayrollPeriod
        $payrollPeriod = PayrollPeriod::where('tenant_id', $tenantId)
            ->where('month_year', $monthYear)
            ->with(['lockedBy', 'policyVersion'])
            ->first();

        // 2. Lấy danh sách PayrollRecord đã tính
        $records = PayrollRecord::where('tenant_id', $tenantId)
            ->where('month_year', $monthYear)
            ->with(['employee'])
            ->get();

        // 3. Lấy danh sách nhân viên để đối chiếu trạng thái duyệt bảng chấm công
        $allEmployees = Employee::where('tenant_id', $tenantId)
            ->whereIn('status', ['OFFICIAL', 'PROBATION', 'ON_LEAVE'])
            ->get();

        $monthlyAttendances = MonthlyAttendance::where('tenant_id', $tenantId)
            ->where('month_year', $monthYear)
            ->get()
            ->keyBy('employee_id');

        $unapprovedEmployees = [];
        foreach ($allEmployees as $emp) {
            $att = $monthlyAttendances->get($emp->id);
            if (!$att || !$att->is_approved) {
                $unapprovedEmployees[] = [
                    'employee_id' => $emp->id,
                    'user_code' => $emp->user_code,
                    'full_name' => $emp->full_name,
                    'status' => $emp->status,
                    'has_timesheet' => (bool) $att,
                    'is_approved' => (bool) ($att?->is_approved ?? false),
                    'total_days' => (float) ($att?->total_standard_days ?? 0),
                    'reason' => !$att
                        ? 'Chưa tổng hợp bảng công tháng'
                        : 'Bảng chấm công chưa được Quản lý duyệt (is_approved = false)',
                ];
            }
        }

        return Inertia::render('Payroll/Index', [
            'monthYear' => $monthYear,
            'payrollPeriod' => $payrollPeriod,
            'records' => $records,
            'unapprovedEmployees' => $unapprovedEmployees,
            'totalEmployeesCount' => $allEmployees->count(),
            'calculatedCount' => $records->count(),
            'unapprovedCount' => count($unapprovedEmployees),
        ]);
    }

    /**
     * Kích hoạt động cơ tính lương cho toàn bộ Tenant theo kỳ tháng
     */
    public function calculate(Request $request): RedirectResponse
    {
        $request->validate([
            'month_year' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $tenantId = (int) ($request->user()->tenant_id ?? 1);
        $monthYear = $request->input('month_year');

        try {
            $result = $this->payrollCalculationService->calculateForMonth($tenantId, $monthYear);

            $msg = "Tính lương kỳ {$monthYear} hoàn tất: Đã tính {$result['calculated_count']} nhân sự. Tự động bỏ qua {$result['skipped_count']} nhân sự chưa duyệt chấm công.";
            return redirect()->route('payroll.index', ['month_year' => $monthYear])
                ->with('success', $msg);
        } catch (\Throwable $e) {
            return redirect()->back()
                ->with('error', 'Lỗi tính toán lương: ' . $e->getMessage());
        }
    }

    /**
     * Khóa sổ kỳ lương bất biến (Chặn mọi sửa đổi hồi tố)
     */
    public function lockPeriod(Request $request): RedirectResponse
    {
        $request->validate([
            'month_year' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $tenantId = (int) ($request->user()->tenant_id ?? 1);
        $monthYear = $request->input('month_year');
        $userId = (int) ($request->user()->id ?? 1);

        $period = PayrollPeriod::firstOrCreate(
            ['tenant_id' => $tenantId, 'month_year' => $monthYear],
            ['status' => 'DRAFT', 'is_locked' => false]
        );

        if ($period->is_locked) {
            return redirect()->back()->with('warning', "Kỳ lương {$monthYear} đã được khóa từ trước.");
        }

        $period->update([
            'is_locked' => true,
            'status' => 'APPROVED',
            'locked_by' => $userId,
            'locked_at' => Carbon::now(),
        ]);

        $this->auditTrailService->log(
            tenantId: $tenantId,
            action: 'LOCK_PAYROLL_PERIOD',
            entityType: 'PayrollPeriod',
            entityId: $period->id,
            oldValues: ['is_locked' => false, 'status' => 'DRAFT'],
            newValues: ['is_locked' => true, 'status' => 'APPROVED', 'locked_at' => Carbon::now()->toIso8601String()],
            performedBy: $userId,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return redirect()->route('payroll.index', ['month_year' => $monthYear])
            ->with('success', "Đã khóa sổ kỳ lương {$monthYear} thành công. Dữ liệu đã được bảo vệ bất biến.");
    }

    /**
     * Phê duyệt nhanh bảng chấm công của nhân viên (Dành cho Quản lý / HR)
     */
    public function approveTimesheet(Request $request, int $employeeId): RedirectResponse
    {
        $tenantId = (int) ($request->user()->tenant_id ?? 1);
        $monthYear = $request->input('month_year', Carbon::now()->format('Y-m'));
        $userId = (int) ($request->user()->id ?? 1);

        $attendance = MonthlyAttendance::firstOrCreate(
            ['tenant_id' => $tenantId, 'employee_id' => $employeeId, 'month_year' => $monthYear],
            ['total_standard_days' => 22.0, 'total_ot_hours' => 0.0]
        );

        $attendance->update([
            'is_approved' => true,
            'approved_by' => $userId,
            'approved_at' => Carbon::now(),
        ]);

        return redirect()->back()
            ->with('success', 'Đã phê duyệt bảng chấm công nhân viên thành công.');
    }
}
