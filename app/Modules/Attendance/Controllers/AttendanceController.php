<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Models\DailyAttendance;
use App\Modules\Attendance\Models\MonthlyAttendance;
use App\Modules\Attendance\Models\RawAttendanceLog;
use App\Modules\Attendance\Services\AttendancePipelineService;
use App\Modules\Attendance\Services\TimesheetPreviewService;
use App\Modules\Attendance\Services\MonthlyAttendanceService;
use App\Modules\Attendance\Requests\CalculateAttendanceRequest;
use App\Modules\Attendance\Requests\LockMonthRequest;
use App\Modules\CoreHR\Models\Employee;
use App\Enums\UserRole;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function __construct(
        protected AttendancePipelineService $pipelineService,
        protected TimesheetPreviewService $previewService,
        protected MonthlyAttendanceService $monthlyService
    ) {}

    /**
     * Màn hình Lưới Bảng công (Timesheet Grid) - Hỗ trợ xem Đã duyệt & Tạm tính (Preview)
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $tenantId = (int) ($user->tenant_id ?? 1);

        $monthYear = $request->query('month_year', '2026-09');
        $mode = $request->query('mode', 'official'); // 'official' | 'preview'

        // Lấy danh sách nhân viên
        $employeesQuery = Employee::where('tenant_id', $tenantId)->where('status', '!=', 'TERMINATED');

        // Nếu là role EMPLOYEE thông thường, chỉ được phép xem của chính mình
        if ($user->role === UserRole::EMPLOYEE) {
            $selectedEmpId = (int) ($user->employee_id ?? 0);
            $employees = $employeesQuery->where('id', $selectedEmpId)->get();
        } else {
            $employees = $employeesQuery->get();
            $selectedEmpId = (int) $request->query('employee_id', $employees->first()?->id ?? 0);
        }

        $selectedEmployee = Employee::with('currentTimeline')->find($selectedEmpId);

        // Kiểm tra trạng thái khóa của kỳ công tháng
        $isLocked = $this->monthlyService->isMonthLocked($tenantId, $monthYear);

        // Kiểm tra quyền thao tác
        $canCalculate = in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::HR_ADMIN], true) && !$isLocked;
        $canLock = in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::HR_ADMIN, UserRole::ACCOUNTANT], true);

        // Dữ liệu bảng công theo chế độ
        if ($mode === 'preview' && $selectedEmpId > 0) {
            $timesheetData = $this->previewService->getPreviewTimesheet($tenantId, $selectedEmpId, $monthYear);
            $days = $timesheetData['days'];
            $summary = $timesheetData['summary'];
        } else {
            // Chế độ Chính thức (Official)
            $startOfMonth = Carbon::parse($monthYear . '-01')->startOfMonth();
            $endOfMonth = $startOfMonth->copy()->endOfMonth();

            $attendances = DailyAttendance::with(['roster.shift'])
                ->where('tenant_id', $tenantId)
                ->where('employee_id', $selectedEmpId)
                ->whereBetween('work_date', [$startOfMonth->format('Y-m-d'), $endOfMonth->format('Y-m-d')])
                ->orderBy('work_date')
                ->get()
                ->keyBy(fn ($item) => Carbon::parse($item->work_date)->format('Y-m-d'));

            $days = [];
            $period = CarbonPeriod::create($startOfMonth, $endOfMonth);

            $totalStdDays = 0.0;
            $totalStdHours = 0.0;
            $totalOt = 0.0;
            $totalLate = 0;
            $totalPenalty = 0.0;

            foreach ($period as $date) {
                $dateStr = $date->format('Y-m-d');
                $item = $attendances->get($dateStr);

                $stdHours = $item ? (float) $item->standard_hours : 0.00;
                $otHours = $item ? (float) ($item->ot_day_hours + $item->ot_night_hours + $item->ot_weekend_hours + $item->ot_holiday_hours) : 0.00;
                $lateMins = $item ? (int) ($item->late_minutes + $item->early_minutes) : 0;
                $penalty = $item ? (float) $item->late_penalty_amount : 0.00;

                $totalStdHours += $stdHours;
                $totalStdDays += ($stdHours >= 8.0) ? 1.0 : round($stdHours / 8.0, 2);
                $totalOt += $otHours;
                $totalLate += $lateMins;
                $totalPenalty += $penalty;

                $days[] = [
                    'work_date' => $dateStr,
                    'day_name' => $date->locale('vi')->isoFormat('dddd'),
                    'is_weekend' => $date->isWeekend(),
                    'roster' => $item?->roster ? [
                        'id' => $item->roster->id,
                        'shift' => $item->roster->shift ? [
                            'code' => $item->roster->shift->code,
                            'name' => $item->roster->shift->name,
                            'start_time' => $item->roster->shift->start_time,
                            'end_time' => $item->roster->shift->end_time,
                        ] : null,
                    ] : null,
                    'actual_in' => $item?->actual_in ? Carbon::parse($item->actual_in)->format('H:i:s') : null,
                    'actual_out' => $item?->actual_out ? Carbon::parse($item->actual_out)->format('H:i:s') : null,
                    'standard_hours' => $stdHours,
                    'late_minutes' => $item ? (int) $item->late_minutes : 0,
                    'early_minutes' => $item ? (int) $item->early_minutes : 0,
                    'late_penalty_amount' => $penalty,
                    'ot_day_hours' => $item ? (float) $item->ot_day_hours : 0.00,
                    'ot_night_hours' => $item ? (float) $item->ot_night_hours : 0.00,
                    'ot_weekend_hours' => $item ? (float) $item->ot_weekend_hours : 0.00,
                    'ot_holiday_hours' => $item ? (float) $item->ot_holiday_hours : 0.00,
                    'status' => $item ? $item->status : ($date->isWeekend() ? 'PRESENT' : 'ABSENT'),
                    'is_preview_modified' => false,
                    'pending_request_type' => null,
                    'preview_note' => null,
                ];
            }

            $summary = [
                'total_standard_days' => round($totalStdDays, 2),
                'total_standard_hours' => round($totalStdHours, 2),
                'total_ot_hours' => round($totalOt, 2),
                'total_late_minutes' => $totalLate,
                'total_penalty_amount' => round($totalPenalty, 2),
                'pending_requests_count' => 0,
            ];
        }

        return Inertia::render('Attendance/Index', [
            'employees' => $employees,
            'selectedEmployee' => $selectedEmployee,
            'monthYear' => $monthYear,
            'mode' => $mode,
            'isLocked' => $isLocked,
            'days' => $days,
            'summary' => $summary,
            'canCalculate' => $canCalculate,
            'canLock' => $canLock,
        ]);
    }

    /**
     * Chạy tính toán lại công cho khoảng thời gian (Trigger 7-Pipe Pipeline)
     */
    public function calculate(CalculateAttendanceRequest $request): RedirectResponse
    {
        $user = Auth::user();
        if (!in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::HR_ADMIN], true)) {
            abort(403, 'Bạn không có quyền tính toán lại bảng công.');
        }

        $tenantId = (int) ($user->tenant_id ?? 1);
        $fromDate = Carbon::parse($request->input('from_date'));
        $toDate = Carbon::parse($request->input('to_date'));
        $empId = $request->filled('employee_id') ? (int) $request->input('employee_id') : null;

        // Kiểm tra tháng có bị khóa không
        $monthYear = $fromDate->format('Y-m');
        if ($this->monthlyService->isMonthLocked($tenantId, $monthYear)) {
            return back()->with('error', "Kỳ công tháng {$monthYear} đã được khóa, không thể tính toán lại.");
        }

        $count = $this->pipelineService->calculateRange($tenantId, $fromDate, $toDate, $empId);

        // Tổng hợp lại công tháng
        if ($empId) {
            $this->monthlyService->aggregateMonth($tenantId, $empId, $monthYear);
        } else {
            $allEmployees = Employee::where('tenant_id', $tenantId)->get();
            foreach ($allEmployees as $e) {
                $this->monthlyService->aggregateMonth($tenantId, (int) $e->id, $monthYear);
            }
        }

        return back()->with('success', "Đã hoàn thành tính toán thành công {$count} lượt ngày công qua 7-Pipe Pipeline.");
    }

    /**
     * Khóa hoặc mở khóa kỳ công tháng
     */
    public function lockMonth(LockMonthRequest $request): RedirectResponse
    {
        $user = Auth::user();
        if (!in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::HR_ADMIN, UserRole::ACCOUNTANT], true)) {
            abort(403, 'Bạn không có quyền khóa hoặc mở khóa kỳ công.');
        }

        $tenantId = (int) ($user->tenant_id ?? 1);
        $monthYear = $request->input('month_year');
        $lock = (bool) $request->input('lock');
        $lockedBy = $user->employee_id;

        $this->monthlyService->lockMonth($tenantId, $monthYear, $lockedBy, $lock);

        $actionText = $lock ? 'Khóa thành công' : 'Đã mở khóa';
        return back()->with('success', "{$actionText} kỳ công tháng {$monthYear}.");
    }

    /**
     * API đối soát xem dữ liệu quẹt thẻ thô từ máy chấm công trong ngày
     */
    public function rawLogs(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = (int) ($user->tenant_id ?? 1);
        $employeeId = (int) $request->query('employee_id');
        $dateStr = $request->query('date', Carbon::today()->format('Y-m-d'));

        // Kiểm tra quyền nếu là EMPLOYEE
        if ($user->role === UserRole::EMPLOYEE && $user->employee_id !== $employeeId) {
            return response()->json(['error' => 'Không được phép xem log của nhân viên khác'], 403);
        }

        $start = Carbon::parse($dateStr)->startOfDay();
        $end = Carbon::parse($dateStr)->endOfDay();

        $logs = RawAttendanceLog::where('tenant_id', $tenantId)
            ->where('employee_id', $employeeId)
            ->whereBetween('punch_time', [$start, $end])
            ->orderBy('punch_time')
            ->get();

        return response()->json([
            'date' => $dateStr,
            'logs' => $logs,
        ]);
    }
}
