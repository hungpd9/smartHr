<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Services;

use App\Modules\Attendance\Models\DailyAttendance;
use App\Modules\Attendance\Models\MonthlyAttendance;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\PolicyEngine\Models\AttendancePolicyConfig;
use App\Modules\PolicyEngine\Services\PolicyResolutionService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MonthlyTimesheetLifecycleService
{
    public function __construct(
        protected MonthlyAttendanceService $monthlyAttendanceService,
        protected PolicyResolutionService $policyResolutionService
    ) {}

    /**
     * Lấy cấu hình chấm công hiện tại của tenant
     */
    public function getPolicyConfig(int $tenantId): AttendancePolicyConfig
    {
        $policyVersion = $this->policyResolutionService->resolveForDate($tenantId, Carbon::today());
        $config = $policyVersion?->attendanceConfig;

        if ($config) {
            return $config;
        }

        // Fallback default config nếu chưa khởi tạo
        return new AttendancePolicyConfig([
            'tenant_id' => $tenantId,
            'enable_auto_submit_timesheet' => true,
            'emp_review_deadline_day' => 3,
            'auto_submit_day' => 4,
            'manager_approval_deadline_day' => 5,
            'higher_manager_deadline_day' => 6,
            'hr_lock_deadline_day' => 7,
            'payroll_payment_day' => 10,
        ]);
    }

    /**
     * Xác định giai đoạn tiến trình chu trình đóng công hiện tại
     */
    public function getCurrentLifecyclePhase(int $tenantId, string $monthYear): array
    {
        $config = $this->getPolicyConfig($tenantId);
        $targetMonth = Carbon::parse($monthYear . '-01');
        $processingMonth = $targetMonth->copy()->addMonth(); // Chu trình của tháng M diễn ra ở tháng M+1
        $now = Carbon::now();

        $empReviewDay = (int) ($config->emp_review_deadline_day ?? 3);
        $autoSubmitDay = (int) ($config->auto_submit_day ?? 4);
        $mgrDeadlineDay = (int) ($config->manager_approval_deadline_day ?? 5);
        $higherMgrDeadlineDay = (int) ($config->higher_manager_deadline_day ?? 6);
        $hrLockDeadlineDay = (int) ($config->hr_lock_deadline_day ?? 7);
        $payrollDay = (int) ($config->payroll_payment_day ?? 10);

        // Tính ngày hiện tại so với tháng xử lý chu trình
        $currentDay = $now->day;
        $isProcessingMonth = $now->format('Y-m') === $processingMonth->format('Y-m');
        $isPastProcessingMonth = $now->greaterThan($processingMonth->copy()->endOfMonth());
        $isBeforeProcessingMonth = $now->lessThan($processingMonth->copy()->startOfMonth());

        $step = 1;
        $phaseKey = 'EMPLOYEE_REVIEW';
        $phaseTitle = 'Nhân viên rà soát & Bổ sung đơn từ';
        $phaseDesc = "Từ ngày 1 đến ngày {$empReviewDay}, nhân viên kiểm tra công và nộp bổ sung các đơn nghỉ phép, OT, chế độ.";

        if ($isPastProcessingMonth) {
            $step = 6;
            $phaseKey = 'ARCHIVED';
            $phaseTitle = 'Đã thanh toán lương & Lưu trữ';
            $phaseDesc = 'Kỳ công và quyết toán lương đã hoàn tất lưu trữ an toàn.';
        } elseif ($isBeforeProcessingMonth) {
            $step = 0;
            $phaseKey = 'IN_PROGRESS';
            $phaseTitle = 'Đang trong tháng làm việc';
            $phaseDesc = "Kỳ công tháng {$monthYear} đang diễn ra. Chu trình đóng công sẽ bắt đầu từ ngày 01/{$processingMonth->format('m/Y')}.";
        } elseif ($isProcessingMonth) {
            if ($currentDay <= $empReviewDay) {
                $step = 1;
                $phaseKey = 'EMPLOYEE_REVIEW';
                $phaseTitle = 'Nhân viên rà soát & Bổ sung đơn từ';
                $phaseDesc = "Hạn chót nhân viên nộp đơn & gửi bảng công: ngày {$empReviewDay}/{$processingMonth->format('m')}.";
            } elseif ($currentDay == $autoSubmitDay) {
                $step = 2;
                $phaseKey = 'AUTO_SUBMIT';
                $phaseTitle = 'Tự động chốt & Gửi bảng công lên Quản lý';
                $phaseDesc = "Hệ thống tự động nộp bảng công cho các nhân viên chưa kịp gửi thủ công.";
            } elseif ($currentDay <= $mgrDeadlineDay) {
                $step = 3;
                $phaseKey = 'MANAGER_APPROVAL';
                $phaseTitle = 'Quản lý trực tiếp kiểm tra & Phê duyệt';
                $phaseDesc = "Quản lý phòng ban xem xét bảng công và đơn từ của cấp dưới (Hạn: ngày {$mgrDeadlineDay}/{$processingMonth->format('m')}).";
            } elseif ($currentDay <= $higherMgrDeadlineDay) {
                $step = 4;
                $phaseKey = 'HIGHER_MANAGER_APPROVAL';
                $phaseTitle = 'Quản lý cấp cao / Khối rà soát';
                $phaseDesc = "Lãnh đạo khối kiểm tra công toàn bộ bộ phận (Hạn: ngày {$higherMgrDeadlineDay}/{$processingMonth->format('m')}).";
            } elseif ($currentDay <= $hrLockDeadlineDay) {
                $step = 5;
                $phaseKey = 'HR_LOCK';
                $phaseTitle = 'Trưởng phòng HR / Giám đốc chốt & Khóa công';
                $phaseDesc = "Khóa bảng công tháng {$monthYear} để chuyển sang tính lương (Hạn: ngày {$hrLockDeadlineDay}/{$processingMonth->format('m')}).";
            } else {
                $step = 6;
                $phaseKey = 'PAYROLL_ACCOUNTING';
                $phaseTitle = 'Kế toán quyết toán & Trả lương';
                $phaseDesc = "Kế toán tính toán chi trả lương (Kế hoạch thanh toán: ngày {$payrollDay}/{$processingMonth->format('m')}).";
            }
        }

        return [
            'target_month' => $monthYear,
            'processing_month' => $processingMonth->format('Y-m'),
            'current_day' => $currentDay,
            'is_processing_month' => $isProcessingMonth,
            'configs' => [
                'enable_auto_submit' => (bool) ($config->enable_auto_submit_timesheet ?? true),
                'emp_review_deadline_day' => $empReviewDay,
                'auto_submit_day' => $autoSubmitDay,
                'manager_approval_deadline_day' => $mgrDeadlineDay,
                'higher_manager_deadline_day' => $higherMgrDeadlineDay,
                'hr_lock_deadline_day' => $hrLockDeadlineDay,
                'payroll_payment_day' => $payrollDay,
            ],
            'current_step' => $step,
            'phase_key' => $phaseKey,
            'phase_title' => $phaseTitle,
            'phase_desc' => $phaseDesc,
            'can_employee_submit' => ($isProcessingMonth && $currentDay <= $empReviewDay) || $isBeforeProcessingMonth,
            'can_manager_approve' => in_array($phaseKey, ['MANAGER_APPROVAL', 'HIGHER_MANAGER_APPROVAL', 'HR_LOCK', 'AUTO_SUBMIT'], true),
            'can_hr_lock' => in_array($phaseKey, ['HR_LOCK', 'PAYROLL_ACCOUNTING', 'ARCHIVED'], true),
        ];
    }

    /**
     * Nhân viên nộp bảng công tháng lên quản lý (hoặc Hệ thống tự động submit)
     */
    public function submitTimesheet(int $tenantId, int $employeeId, string $monthYear, bool $isAuto = false): MonthlyAttendance
    {
        $attendance = MonthlyAttendance::where('tenant_id', $tenantId)
            ->where('employee_id', $employeeId)
            ->where('month_year', $monthYear)
            ->first();

        if (!$attendance) {
            $attendance = $this->monthlyAttendanceService->aggregateMonth($tenantId, $employeeId, $monthYear);
        }

        if ($attendance->is_locked) {
            throw new \DomainException("Bảng công tháng {$monthYear} đã được khóa, không thể nộp.");
        }

        $attendance->update([
            'submission_status' => 'SUBMITTED',
            'submission_type' => $isAuto ? 'AUTO_SUBMITTED' : 'MANUAL',
            'submitted_at' => Carbon::now(),
            'rejection_reason' => null,
            'rejected_by' => null,
            'rejected_at' => null,
        ]);

        return $attendance->fresh();
    }

    /**
     * Tự động gửi bảng công tháng cho tất cả nhân viên chưa submit khi đến hạn
     */
    public function autoSubmitExpiredTimesheets(int $tenantId, string $monthYear): int
    {
        $config = $this->getPolicyConfig($tenantId);
        if (!$config->enable_auto_submit_timesheet) {
            return 0;
        }

        $employees = Employee::where('tenant_id', $tenantId)
            ->where('status', '!=', 'TERMINATED')
            ->get();

        $autoSubmittedCount = 0;

        foreach ($employees as $employee) {
            $attendance = MonthlyAttendance::where('tenant_id', $tenantId)
                ->where('employee_id', $employee->id)
                ->where('month_year', $monthYear)
                ->first();

            // Nếu chưa có bản ghi, aggregate công tháng
            if (!$attendance) {
                $attendance = $this->monthlyAttendanceService->aggregateMonth($tenantId, (int) $employee->id, $monthYear);
            }

            // Chỉ auto-submit nếu đang ở EMPLOYEE_REVIEW, REJECTED hoặc chưa submit
            if (!$attendance->is_locked && in_array($attendance->submission_status, ['EMPLOYEE_REVIEW', 'REJECTED', null], true)) {
                $attendance->update([
                    'submission_status' => 'SUBMITTED',
                    'submission_type' => 'AUTO_SUBMITTED',
                    'submitted_at' => Carbon::now(),
                ]);
                $autoSubmittedCount++;
            }
        }

        return $autoSubmittedCount;
    }

    /**
     * Quản lý phê duyệt bảng công cá nhân
     */
    public function approveByManager(int $tenantId, int $attendanceId, int $managerEmployeeId, bool $isHigherManager = false): MonthlyAttendance
    {
        $attendance = MonthlyAttendance::where('tenant_id', $tenantId)->findOrFail($attendanceId);

        if ($attendance->is_locked) {
            throw new \DomainException('Bảng công đã bị khóa, không thể thao tác.');
        }

        $newStatus = $isHigherManager ? 'HIGHER_MANAGER_APPROVED' : 'MANAGER_APPROVED';
        $attendance->update([
            'submission_status' => $newStatus,
            'rejection_reason' => null,
            'rejected_by' => null,
            'rejected_at' => null,
        ]);

        return $attendance;
    }

    /**
     * Quản lý từ chối bảng công, yêu cầu nhân viên giải trình hoặc bổ sung đơn
     */
    public function rejectByManager(int $tenantId, int $attendanceId, int $managerEmployeeId, string $reason): MonthlyAttendance
    {
        $attendance = MonthlyAttendance::where('tenant_id', $tenantId)->findOrFail($attendanceId);

        if ($attendance->is_locked) {
            throw new \DomainException('Bảng công đã bị khóa, không thể từ chối.');
        }

        $attendance->update([
            'submission_status' => 'REJECTED',
            'rejection_reason' => $reason,
            'rejected_by' => $managerEmployeeId,
            'rejected_at' => Carbon::now(),
        ]);

        return $attendance;
    }

    /**
     * Quản lý phê duyệt hàng loạt bảng công
     */
    public function bulkApprove(int $tenantId, array $attendanceIds, int $managerEmployeeId, bool $isHigherManager = false): int
    {
        $newStatus = $isHigherManager ? 'HIGHER_MANAGER_APPROVED' : 'MANAGER_APPROVED';

        return MonthlyAttendance::where('tenant_id', $tenantId)
            ->whereIn('id', $attendanceIds)
            ->where('is_locked', false)
            ->update([
                'submission_status' => $newStatus,
                'rejection_reason' => null,
                'rejected_by' => null,
                'rejected_at' => null,
            ]);
    }

    /**
     * Lấy dữ liệu ma trận chấm công theo tháng (WorkMonth)
     */
    public function getWorkMonthMatrix(
        int $tenantId,
        string $monthYear,
        ?int $branchId = null,
        ?int $departmentId = null,
        ?string $search = null
    ): array {
        $startOfMonth = Carbon::parse($monthYear . '-01')->startOfMonth();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        // Danh sách ngày trong tháng
        $calendarDays = [];
        $period = CarbonPeriod::create($startOfMonth, $endOfMonth);
        foreach ($period as $date) {
            $calendarDays[] = [
                'date' => $date->format('Y-m-d'),
                'day_number' => $date->day,
                'day_name' => $date->locale('vi')->isoFormat('dd'),
                'is_weekend' => $date->isWeekend(),
            ];
        }

        // Truy vấn nhân viên kèm thông tin phòng ban, chi nhánh, chức vụ
        $empQuery = Employee::with([
            'currentTimeline.department.currentVersion',
            'currentTimeline.department.branch',
            'currentTimeline.position',
        ])
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', 'TERMINATED');

        if ($branchId) {
            $empQuery->whereHas('currentTimeline.department', fn ($q) => $q->where('branch_id', $branchId));
        }

        if ($departmentId) {
            $empQuery->whereHas('currentTimeline', fn ($q) => $q->where('department_id', $departmentId));
        }

        if ($search) {
            $empQuery->where(function ($q) use ($search) {
                $q->where('user_code', 'like', "%{$search}%")
                  ->orWhere('full_name', 'like', "%{$search}%")
                  ->orWhere('attendance_code', 'like', "%{$search}%");
            });
        }

        $employees = $empQuery->orderBy('user_code')->get();
        $employeeIds = $employees->pluck('id')->all();

        // Lấy toàn bộ DailyAttendance của các nhân viên trong tháng
        $dailyRecords = DailyAttendance::where('tenant_id', $tenantId)
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('work_date', [$startOfMonth->format('Y-m-d'), $endOfMonth->format('Y-m-d')])
            ->get()
            ->groupBy('employee_id');

        // Lấy MonthlyAttendance của các nhân viên trong tháng
        $monthlyRecords = MonthlyAttendance::where('tenant_id', $tenantId)
            ->whereIn('employee_id', $employeeIds)
            ->where('month_year', $monthYear)
            ->get()
            ->keyBy('employee_id');

        $rows = [];
        foreach ($employees as $emp) {
            $empDaily = $dailyRecords->get($emp->id, collect())->keyBy(fn ($item) => Carbon::parse($item->work_date)->format('Y-m-d'));
            $empMonthly = $monthlyRecords->get($emp->id);

            $dayCells = [];
            $totalStdDays = 0.0;
            $totalOt = 0.0;
            $totalLateMins = 0;
            $totalPenalty = 0.0;

            foreach ($calendarDays as $cal) {
                $dateStr = $cal['date'];
                $item = $empDaily->get($dateStr);

                if ($item) {
                    $stdHours = (float) $item->standard_hours;
                    $dayOt = (float) ($item->ot_day_hours + $item->ot_night_hours + $item->ot_weekend_hours + $item->ot_holiday_hours);
                    $lateMins = (int) ($item->late_minutes + $item->early_minutes);
                    $penalty = (float) $item->late_penalty_amount;

                    $totalOt += $dayOt;
                    $totalLateMins += $lateMins;
                    $totalPenalty += $penalty;

                    // Xác định ký hiệu chấm công
                    if ($stdHours >= 8.0) {
                        $symbol = 'X';
                        $totalStdDays += 1.0;
                    } elseif ($stdHours > 0) {
                        $symbol = (string) round($stdHours / 8.0, 1);
                        $totalStdDays += round($stdHours / 8.0, 2);
                    } elseif ($cal['is_weekend']) {
                        $symbol = 'OFF';
                    } elseif ($item->status === 'ABSENT') {
                        $symbol = 'V';
                    } else {
                        $symbol = $item->status ?? '0';
                    }

                    $cell = [
                        'symbol' => $symbol,
                        'std_hours' => $stdHours,
                        'ot_hours' => $dayOt,
                        'late_minutes' => $lateMins,
                        'is_late' => $lateMins > 0,
                        'actual_in' => $item->actual_in ? Carbon::parse($item->actual_in)->format('H:i') : null,
                        'actual_out' => $item->actual_out ? Carbon::parse($item->actual_out)->format('H:i') : null,
                    ];
                } else {
                    $symbol = $cal['is_weekend'] ? 'OFF' : '-';
                    $cell = [
                        'symbol' => $symbol,
                        'std_hours' => 0.0,
                        'ot_hours' => 0.0,
                        'late_minutes' => 0,
                        'is_late' => false,
                        'actual_in' => null,
                        'actual_out' => null,
                    ];
                }

                $dayCells[$dateStr] = $cell;
            }

            $dept = $emp->currentTimeline?->department;
            $rows[] = [
                'employee_id' => $emp->id,
                'user_code' => $emp->user_code,
                'attendance_code' => $emp->attendance_code,
                'full_name' => $emp->full_name,
                'department_name' => $dept?->currentVersion?->name ?? $dept?->code ?? 'Chưa gán',
                'branch_name' => $dept?->branch?->name ?? 'Văn phòng chính',
                'position_title' => $emp->currentTimeline?->position?->title ?? $emp->currentTimeline?->job_title ?? 'Nhân viên',
                'attendance_id' => $empMonthly?->id,
                'submission_status' => $empMonthly?->submission_status ?? 'EMPLOYEE_REVIEW',
                'submission_type' => $empMonthly?->submission_type ?? 'MANUAL',
                'submitted_at' => $empMonthly?->submitted_at?->format('d/m/Y H:i'),
                'rejection_reason' => $empMonthly?->rejection_reason,
                'is_locked' => (bool) ($empMonthly?->is_locked ?? false),
                'total_standard_days' => round($empMonthly ? (float) $empMonthly->total_standard_days : $totalStdDays, 2),
                'total_ot_hours' => round($empMonthly ? (float) $empMonthly->total_ot_hours : $totalOt, 2),
                'total_late_minutes' => $empMonthly ? (int) $empMonthly->total_late_minutes : $totalLateMins,
                'total_penalty_amount' => round($empMonthly ? (float) $empMonthly->total_penalty_amount : $totalPenalty, 2),
                'days' => $dayCells,
            ];
        }

        return [
            'calendar_days' => $calendarDays,
            'rows' => $rows,
            'total_count' => count($rows),
        ];
    }

    /**
     * Lấy dữ liệu điểm danh theo ngày cụ thể (WorkDate Overview)
     */
    public function getWorkDateOverview(
        int $tenantId,
        string $workDate,
        ?int $branchId = null,
        ?int $departmentId = null,
        ?string $search = null
    ): array {
        $date = Carbon::parse($workDate);

        $empQuery = Employee::with([
            'currentTimeline.department.currentVersion',
            'currentTimeline.department.branch',
            'currentTimeline.position',
        ])
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', 'TERMINATED');

        if ($branchId) {
            $empQuery->whereHas('currentTimeline.department', fn ($q) => $q->where('branch_id', $branchId));
        }

        if ($departmentId) {
            $empQuery->whereHas('currentTimeline', fn ($q) => $q->where('department_id', $departmentId));
        }

        if ($search) {
            $empQuery->where(function ($q) use ($search) {
                $q->where('user_code', 'like', "%{$search}%")
                  ->orWhere('full_name', 'like', "%{$search}%")
                  ->orWhere('attendance_code', 'like', "%{$search}%");
            });
        }

        $employees = $empQuery->orderBy('user_code')->get();
        $employeeIds = $employees->pluck('id')->all();

        $dailyRecords = DailyAttendance::with(['roster.shift'])
            ->where('tenant_id', $tenantId)
            ->whereIn('employee_id', $employeeIds)
            ->where('work_date', $date->format('Y-m-d'))
            ->get()
            ->keyBy('employee_id');

        $rows = [];
        $presentCount = 0;
        $lateCount = 0;
        $earlyCount = 0;
        $absentCount = 0;
        $otCount = 0;

        foreach ($employees as $emp) {
            $item = $dailyRecords->get($emp->id);
            $dept = $emp->currentTimeline?->department;

            $actualIn = $item?->actual_in ? Carbon::parse($item->actual_in)->format('H:i:s') : null;
            $actualOut = $item?->actual_out ? Carbon::parse($item->actual_out)->format('H:i:s') : null;
            $stdHours = $item ? (float) $item->standard_hours : 0.00;
            $lateMins = $item ? (int) $item->late_minutes : 0;
            $earlyMins = $item ? (int) $item->early_minutes : 0;
            $otHours = $item ? (float) ($item->ot_day_hours + $item->ot_night_hours + $item->ot_weekend_hours + $item->ot_holiday_hours) : 0.00;

            $status = $item?->status ?? ($date->isWeekend() ? 'WEEKEND' : 'NO_DATA');

            if ($actualIn) {
                $presentCount++;
            } elseif (!$date->isWeekend()) {
                $absentCount++;
            }

            if ($lateMins > 0) $lateCount++;
            if ($earlyMins > 0) $earlyCount++;
            if ($otHours > 0) $otCount++;

            $rows[] = [
                'employee_id' => $emp->id,
                'user_code' => $emp->user_code,
                'attendance_code' => $emp->attendance_code,
                'full_name' => $emp->full_name,
                'department_name' => $dept?->currentVersion?->name ?? $dept?->code ?? 'Chưa gán',
                'branch_name' => $dept?->branch?->name ?? 'Văn phòng chính',
                'position_title' => $emp->currentTimeline?->position?->title ?? 'Nhân viên',
                'shift_name' => $item?->roster?->shift?->name ?? 'Ca hành chính',
                'shift_time' => $item?->roster?->shift ? ($item->roster->shift->start_time . ' - ' . $item->roster->shift->end_time) : '08:00 - 17:30',
                'actual_in' => $actualIn,
                'actual_out' => $actualOut,
                'standard_hours' => $stdHours,
                'late_minutes' => $lateMins,
                'early_minutes' => $earlyMins,
                'ot_hours' => $otHours,
                'late_penalty_amount' => $item ? (float) $item->late_penalty_amount : 0.00,
                'status' => $status,
            ];
        }

        return [
            'work_date' => $date->format('Y-m-d'),
            'day_name' => $date->locale('vi')->isoFormat('dddd'),
            'is_weekend' => $date->isWeekend(),
            'stats' => [
                'total_employees' => count($rows),
                'present_count' => $presentCount,
                'late_count' => $lateCount,
                'early_count' => $earlyCount,
                'absent_count' => $absentCount,
                'ot_count' => $otCount,
            ],
            'rows' => $rows,
        ];
    }
}
