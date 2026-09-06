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
use App\Modules\Attendance\Services\MonthlyTimesheetLifecycleService;
use App\Modules\Attendance\Requests\CalculateAttendanceRequest;
use App\Modules\Attendance\Requests\LockMonthRequest;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\CoreHR\Models\Branch;
use App\Modules\CoreHR\Models\Department;
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
        protected MonthlyAttendanceService $monthlyService,
        protected MonthlyTimesheetLifecycleService $lifecycleService
    ) {}

    /**
     * Màn hình Bảng Chấm công Đa chế độ:
     * 1. WorkMonth: Ma trận tháng 31 ngày toàn công ty theo phòng ban/chi nhánh
     * 2. WorkDate: Điểm danh chi tiết theo từng ngày làm việc
     * 3. Detail: Chi tiết từng cá nhân (Official vs Preview)
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $tenantId = (int) ($user->tenant_id ?? 1);

        $defaultView = $user->role === UserRole::EMPLOYEE ? 'workmonth' : 'workmonth';
        $view = $request->query('view', $defaultView); // 'workmonth' | 'workdate' | 'detail'
        $monthYear = $request->query('month_year', '2026-09');
        $workDate = $request->query('work_date', Carbon::today()->format('Y-m-d'));
        $mode = $request->query('mode', 'official'); // 'official' | 'preview'

        $branchId = $request->filled('branch_id') ? (int) $request->query('branch_id') : null;
        $departmentId = $request->filled('department_id') ? (int) $request->query('department_id') : null;
        $search = $request->query('search');

        // Danh sách Chi nhánh & Phòng ban cho bộ lọc
        $branches = Branch::where('tenant_id', $tenantId)->get(['id', 'name', 'code']);
        $departments = Department::with('currentVersion')->where('tenant_id', $tenantId)->get()->map(fn ($d) => [
            'id' => $d->id,
            'name' => $d->currentVersion?->name ?? $d->code,
            'code' => $d->code,
            'branch_id' => $d->branch_id,
        ]);

        // Trạng thái chu trình đóng công tháng (Zero-Hardcode Lifecycle Phase)
        $lifecyclePhase = $this->lifecycleService->getCurrentLifecyclePhase($tenantId, $monthYear);

        // Kiểm tra trạng thái khóa của kỳ công tháng
        $isLocked = $this->monthlyService->isMonthLocked($tenantId, $monthYear);

        // Kiểm tra quyền hạn
        $canCalculate = in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::HR_ADMIN], true) && !$isLocked;
        $canLock = in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::HR_ADMIN, UserRole::ACCOUNTANT], true);
        $canApprove = in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::HR_ADMIN, UserRole::DEPARTMENT_MANAGER], true);

        // Lấy danh sách nhân viên
        $employeesQuery = Employee::where('tenant_id', $tenantId)->where('status', '!=', 'TERMINATED');
        if ($user->role === UserRole::EMPLOYEE) {
            $selectedEmpId = (int) ($user->employee_id ?? 0);
            $employees = $employeesQuery->where('id', $selectedEmpId)->get();
        } else {
            $employees = $employeesQuery->get();
            $selectedEmpId = (int) $request->query('employee_id', $employees->first()?->id ?? 0);
        }

        $selectedEmployee = Employee::with('currentTimeline')->find($selectedEmpId);

        // Dữ liệu theo từng view
        $workMonthData = null;
        $workDateData = null;
        $detailDays = [];
        $detailSummary = [];

        if ($view === 'workmonth') {
            $workMonthData = $this->lifecycleService->getWorkMonthMatrix(
                $tenantId,
                $monthYear,
                $branchId,
                $departmentId,
                $search
            );
        } elseif ($view === 'workdate') {
            $workDateData = $this->lifecycleService->getWorkDateOverview(
                $tenantId,
                $workDate,
                $branchId,
                $departmentId,
                $search
            );
        } else {
            // View 'detail' cá nhân
            if ($mode === 'preview' && $selectedEmpId > 0) {
                $timesheetData = $this->previewService->getPreviewTimesheet($tenantId, $selectedEmpId, $monthYear);
                $detailDays = $timesheetData['days'];
                $detailSummary = $timesheetData['summary'];
            } else {
                $startOfMonth = Carbon::parse($monthYear . '-01')->startOfMonth();
                $endOfMonth = $startOfMonth->copy()->endOfMonth();

                $attendances = DailyAttendance::with(['roster.shift'])
                    ->where('tenant_id', $tenantId)
                    ->where('employee_id', $selectedEmpId)
                    ->whereBetween('work_date', [$startOfMonth->format('Y-m-d'), $endOfMonth->format('Y-m-d')])
                    ->orderBy('work_date')
                    ->get()
                    ->keyBy(fn ($item) => Carbon::parse($item->work_date)->format('Y-m-d'));

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

                    $detailDays[] = [
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

                $detailSummary = [
                    'total_standard_days' => round($totalStdDays, 2),
                    'total_standard_hours' => round($totalStdHours, 2),
                    'total_ot_hours' => round($totalOt, 2),
                    'total_late_minutes' => $totalLate,
                    'total_penalty_amount' => round($totalPenalty, 2),
                    'pending_requests_count' => 0,
                ];
            }
        }

        return Inertia::render('Attendance/Index', [
            'view' => $view,
            'monthYear' => $monthYear,
            'workDate' => $workDate,
            'mode' => $mode,
            'isLocked' => $isLocked,
            'filters' => [
                'branch_id' => $branchId,
                'department_id' => $departmentId,
                'search' => $search,
            ],
            'branches' => $branches,
            'departments' => $departments,
            'lifecyclePhase' => $lifecyclePhase,
            'workMonthData' => $workMonthData,
            'workDateData' => $workDateData,
            'employees' => $employees,
            'selectedEmployee' => $selectedEmployee,
            'days' => $detailDays,
            'summary' => $detailSummary,
            'canCalculate' => $canCalculate,
            'canLock' => $canLock,
            'canApprove' => $canApprove,
        ]);
    }

    /**
     * Nhân viên nộp bảng công tháng của mình lên Quản lý phê duyệt
     */
    public function submitTimesheet(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $tenantId = (int) ($user->tenant_id ?? 1);
        $monthYear = $request->input('month_year', '2026-09');

        $employeeId = $request->filled('employee_id') && in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::HR_ADMIN], true)
            ? (int) $request->input('employee_id')
            : (int) ($user->employee_id ?? 0);

        if ($employeeId <= 0) {
            return back()->with('error', 'Không tìm thấy hồ sơ nhân viên hợp lệ.');
        }

        try {
            $this->lifecycleService->submitTimesheet($tenantId, $employeeId, $monthYear, false);
            return back()->with('success', "Đã nộp thành công bảng công tháng {$monthYear} lên Quản lý phê duyệt.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Quản lý duyệt 1 bảng công của nhân viên
     */
    public function approveTimesheet(Request $request, int $id): RedirectResponse
    {
        $user = Auth::user();
        if (!in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::HR_ADMIN, UserRole::DEPARTMENT_MANAGER], true)) {
            abort(403, 'Bạn không có quyền phê duyệt bảng công.');
        }

        $tenantId = (int) ($user->tenant_id ?? 1);
        $managerEmpId = (int) ($user->employee_id ?? 0);
        $isHigher = in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::HR_ADMIN], true);

        try {
            $this->lifecycleService->approveByManager($tenantId, $id, $managerEmpId, $isHigher);
            return back()->with('success', 'Đã phê duyệt bảng công thành công.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Quản lý từ chối bảng công (yêu cầu nhân viên giải trình hoặc bổ sung đơn từ)
     */
    public function rejectTimesheet(Request $request, int $id): RedirectResponse
    {
        $user = Auth::user();
        if (!in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::HR_ADMIN, UserRole::DEPARTMENT_MANAGER], true)) {
            abort(403, 'Bạn không có quyền từ chối bảng công.');
        }

        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $tenantId = (int) ($user->tenant_id ?? 1);
        $managerEmpId = (int) ($user->employee_id ?? 0);
        $reason = (string) $request->input('reason');

        try {
            $this->lifecycleService->rejectByManager($tenantId, $id, $managerEmpId, $reason);
            return back()->with('success', 'Đã từ chối và gửi phản hồi yêu cầu nhân viên bổ sung đơn từ.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Quản lý phê duyệt hàng loạt bảng công
     */
    public function bulkApprove(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (!in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::HR_ADMIN, UserRole::DEPARTMENT_MANAGER], true)) {
            abort(403, 'Bạn không có quyền phê duyệt hàng loạt.');
        }

        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        $tenantId = (int) ($user->tenant_id ?? 1);
        $managerEmpId = (int) ($user->employee_id ?? 0);
        $ids = (array) $request->input('ids');
        $isHigher = in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::HR_ADMIN], true);

        $count = $this->lifecycleService->bulkApprove($tenantId, $ids, $managerEmpId, $isHigher);

        return back()->with('success', "Đã phê duyệt thành công {$count} bảng công.");
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
