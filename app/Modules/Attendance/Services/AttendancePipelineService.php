<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Services;

use App\Modules\Attendance\Pipelines\AttendanceCalculationContext;
use App\Modules\Attendance\Pipelines\Pipes\PairInOutPipe;
use App\Modules\Attendance\Pipelines\Pipes\ShiftMatchingPipe;
use App\Modules\Attendance\Pipelines\Pipes\PolicyResolverPipe;
use App\Modules\Attendance\Pipelines\Pipes\ChildWelfarePipe;
use App\Modules\Attendance\Pipelines\Pipes\LateEarlyPipe;
use App\Modules\Attendance\Pipelines\Pipes\ProgressivePenaltyPipe;
use App\Modules\Attendance\Pipelines\Pipes\TieredOvertimePipe;
use App\Modules\Attendance\Models\DailyAttendance;
use App\Modules\CoreHR\Models\Employee;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\DB;

class AttendancePipelineService
{
    protected array $pipes = [
        PairInOutPipe::class,
        ShiftMatchingPipe::class,
        PolicyResolverPipe::class,
        ChildWelfarePipe::class,
        LateEarlyPipe::class,
        ProgressivePenaltyPipe::class,
        TieredOvertimePipe::class,
    ];

    /**
     * Chạy tính toán công cho 1 nhân viên tại 1 ngày cụ thể
     */
    public function calculateDate(int $tenantId, int $employeeId, Carbon $workDate): DailyAttendance
    {
        $context = new AttendanceCalculationContext($tenantId, $employeeId, $workDate);

        // Đếm số lần vi phạm đã có trong tháng tính từ đầu tháng đến ngày hôm trước
        $startOfMonth = $workDate->copy()->startOfMonth();
        $previousViolations = DailyAttendance::where('tenant_id', $tenantId)
            ->where('employee_id', $employeeId)
            ->whereBetween('work_date', [$startOfMonth->format('Y-m-d'), $workDate->copy()->subDay()->format('Y-m-d')])
            ->where(function ($q) {
                $q->where('late_minutes', '>', 0)
                  ->orWhere('early_minutes', '>', 0);
            })
            ->count();

        $context->violationCountThisMonth = $previousViolations + 1;

        // Chạy qua 7-Pipe Pipeline
        /** @var AttendanceCalculationContext $result */
        $result = app(Pipeline::class)
            ->send($context)
            ->through($this->pipes)
            ->thenReturn();

        // Lưu / cập nhật vào CSDL (bảng daily_attendances)
        return DailyAttendance::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'employee_id' => $employeeId,
                'work_date' => $workDate->format('Y-m-d'),
            ],
            [
                'roster_id' => $result->roster?->id,
                'actual_in' => $result->actualIn,
                'actual_out' => $result->actualOut,
                'standard_hours' => $result->standardHours,
                'late_minutes' => $result->lateMinutes,
                'early_minutes' => $result->earlyMinutes,
                'late_penalty_amount' => $result->latePenaltyAmount,
                'ot_day_hours' => $result->otDayHours,
                'ot_night_hours' => $result->otNightHours,
                'ot_weekend_hours' => $result->otWeekendHours,
                'ot_holiday_hours' => $result->otHolidayHours,
                'status' => $result->status,
            ]
        );
    }

    /**
     * Chạy tính toán công cho 1 nhân viên hoặc toàn bộ nhân viên trong một khoảng ngày
     */
    public function calculateRange(int $tenantId, Carbon $fromDate, Carbon $toDate, ?int $employeeId = null): int
    {
        $employeesQuery = Employee::where('tenant_id', $tenantId)->where('status', '!=', 'TERMINATED');
        if ($employeeId) {
            $employeesQuery->where('id', $employeeId);
        }
        $employees = $employeesQuery->get();

        $period = CarbonPeriod::create($fromDate->copy()->startOfDay(), $toDate->copy()->startOfDay());
        $count = 0;

        DB::transaction(function () use ($tenantId, $employees, $period, &$count) {
            foreach ($employees as $employee) {
                foreach ($period as $date) {
                    $this->calculateDate($tenantId, (int) $employee->id, $date);
                    $count++;
                }
            }
        });

        return $count;
    }
}
