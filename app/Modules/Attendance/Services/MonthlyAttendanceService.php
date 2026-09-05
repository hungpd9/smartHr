<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Services;

use App\Modules\Attendance\Models\DailyAttendance;
use App\Modules\Attendance\Models\MonthlyAttendance;
use App\Modules\CoreHR\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MonthlyAttendanceService
{
    /**
     * Tổng hợp công tháng cho 1 nhân viên từ các bản ghi DailyAttendance
     */
    public function aggregateMonth(int $tenantId, int $employeeId, string $monthYear): MonthlyAttendance
    {
        $startOfMonth = Carbon::parse($monthYear . '-01')->startOfMonth();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        // Kiểm tra xem tháng đã bị khóa hay chưa
        $existing = MonthlyAttendance::where('tenant_id', $tenantId)
            ->where('employee_id', $employeeId)
            ->where('month_year', $monthYear)
            ->first();

        if ($existing && $existing->is_locked) {
            throw new \DomainException("Kỳ công tháng {$monthYear} đã bị khóa, không thể tổng hợp lại dữ liệu.");
        }

        $dailyRecords = DailyAttendance::where('tenant_id', $tenantId)
            ->where('employee_id', $employeeId)
            ->whereBetween('work_date', [$startOfMonth->format('Y-m-d'), $endOfMonth->format('Y-m-d')])
            ->get();

        $totalStandardDays = 0.0;
        $totalOtHours = 0.0;
        $totalLateMinutes = 0;
        $totalPenaltyAmount = 0.0;

        foreach ($dailyRecords as $record) {
            $stdHours = (float) $record->standard_hours;
            $totalStandardDays += ($stdHours >= 8.0) ? 1.0 : round($stdHours / 8.0, 2);

            $totalOtHours += (float) ($record->ot_day_hours + $record->ot_night_hours + $record->ot_weekend_hours + $record->ot_holiday_hours);
            $totalLateMinutes += (int) ($record->late_minutes + $record->early_minutes);
            $totalPenaltyAmount += (float) $record->late_penalty_amount;
        }

        return MonthlyAttendance::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'employee_id' => $employeeId,
                'month_year' => $monthYear,
            ],
            [
                'total_standard_days' => round($totalStandardDays, 2),
                'total_ot_hours' => round($totalOtHours, 2),
                'total_late_minutes' => $totalLateMinutes,
                'total_penalty_amount' => round($totalPenaltyAmount, 2),
            ]
        );
    }

    /**
     * Khóa hoặc mở khóa kỳ công tháng
     */
    public function lockMonth(int $tenantId, string $monthYear, ?int $lockedByEmployeeId, bool $lock = true): int
    {
        $employees = Employee::where('tenant_id', $tenantId)->get();
        $updatedCount = 0;

        DB::transaction(function () use ($tenantId, $monthYear, $lockedByEmployeeId, $lock, $employees, &$updatedCount) {
            foreach ($employees as $emp) {
                // Đảm bảo có bản ghi monthly_attendance trước khi khóa
                $record = MonthlyAttendance::firstOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'employee_id' => $emp->id,
                        'month_year' => $monthYear,
                    ],
                    [
                        'total_standard_days' => 0.00,
                        'total_ot_hours' => 0.00,
                        'total_late_minutes' => 0,
                        'total_penalty_amount' => 0.00,
                    ]
                );

                $record->update([
                    'is_locked' => $lock,
                    'locked_by' => $lock ? $lockedByEmployeeId : null,
                    'locked_at' => $lock ? Carbon::now() : null,
                ]);

                $updatedCount++;
            }
        });

        return $updatedCount;
    }

    /**
     * Kiểm tra trạng thái khóa của kỳ công
     */
    public function isMonthLocked(int $tenantId, string $monthYear): bool
    {
        return MonthlyAttendance::where('tenant_id', $tenantId)
            ->where('month_year', $monthYear)
            ->where('is_locked', true)
            ->exists();
    }
}
