<?php

declare(strict_types=1);

namespace App\Modules\Rostering\Solvers;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use App\Modules\CoreHR\Models\Employee;
use App\Modules\Rostering\Models\Shift;
use App\Modules\Rostering\Models\Roster;
use App\Modules\Rostering\Enums\RosteringStrategy;
use App\Modules\Rostering\Services\RosteringConstraintValidator;

class AutoRosteringSolverService
{
    public function __construct(
        protected RosteringConstraintValidator $validator
    ) {}

    /**
     * Thuật toán Solver tự động lập lịch phân ca tối ưu thỏa mãn BLLĐ 2019
     * 
     * @param int $tenantId
     * @param string $startDate
     * @param string $endDate
     * @param RosteringStrategy|string $strategy
     * @param array<int> $shiftIds Danh sách các ca mẫu được phép phân bổ
     * @param array<int>|null $employeeIds Danh sách nhân viên (NULL = toàn bộ nhân viên hợp lệ)
     * @param array<string, mixed> $preferences Tiêu chí ưu tiên phân ca
     * @param bool $simulateOnly Chế độ mô phỏng đối soát không ghi CSDL
     * @return array
     */
    public function solve(
        int $tenantId,
        string $startDate,
        string $endDate,
        RosteringStrategy|string $strategy = RosteringStrategy::FAIRNESS,
        array $shiftIds = [],
        ?array $employeeIds = null,
        array $preferences = [],
        bool $simulateOnly = false
    ): array {
        $strat = is_string($strategy) ? RosteringStrategy::from($strategy) : $strategy;
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        $avoidFemaleNight = (bool)($preferences['avoid_female_night'] ?? false);
        $avoidHasChild = (bool)($preferences['avoid_has_child'] ?? false);
        $prioritizeLowBaseSalary = (bool)($preferences['prioritize_low_base_salary'] ?? false);

        // 1. Nạp danh sách ca làm việc
        $shifts = Shift::where('tenant_id', $tenantId)
            ->whereIn('id', $shiftIds)
            ->where('is_active', true)
            ->orderByDesc('is_night_shift')
            ->get();

        if ($shifts->isEmpty()) {
            return [
                'total_shifts_scheduled' => 0,
                'total_night_shifts' => 0,
                'total_rest_days' => 0,
                'employees_count' => 0,
                'violations_prevented' => 0,
                'estimated_night_allowance' => 0.0,
                'employee_breakdown' => [],
                'roster_preview' => [],
                'is_simulated' => $simulateOnly,
            ];
        }

        // 2. Nạp danh sách nhân viên
        $empQuery = Employee::where('tenant_id', $tenantId)
            ->whereIn('status', ['PROBATION', 'OFFICIAL'])
            ->with(['children' => fn($q) => $q->where('is_active', true)]);

        if (!empty($employeeIds)) {
            $empQuery->whereIn('id', $employeeIds);
        }

        $employees = $empQuery->get();
        if ($employees->isEmpty()) {
            return [
                'total_shifts_scheduled' => 0,
                'total_night_shifts' => 0,
                'total_rest_days' => 0,
                'employees_count' => 0,
                'violations_prevented' => 0,
                'estimated_night_allowance' => 0.0,
                'employee_breakdown' => [],
                'roster_preview' => [],
                'is_simulated' => $simulateOnly,
            ];
        }

        // Bảng theo dõi số lượng ca đêm, chi phí phụ cấp và số giờ lũy kế
        $employeeStats = [];
        foreach ($employees as $emp) {
            $baseSalary = ($emp->status === 'PROBATION') ? 8500000.0 : 10000000.0;
            $hourlyRate = $baseSalary / (26 * 8);

            $employeeStats[$emp->id] = [
                'employee' => $emp,
                'night_shifts_count' => 0,
                'day_shifts_count' => 0,
                'rest_days_count' => 0,
                'consecutive_work_days' => 0,
                'total_hours' => 0.0,
                'base_salary' => $baseSalary,
                'hourly_rate' => $hourlyRate,
                'estimated_night_allowance' => 0.0,
            ];
        }

        $scheduledCount = 0;
        $nightShiftsCount = 0;
        $restDaysCount = 0;
        $violationsPreventedCount = 0;
        $totalNightAllowance = 0.0;
        $rosterPreview = [];

        $period = CarbonPeriod::create($start, $end);

        $executionCallback = function () use (
            $tenantId,
            $period,
            $employees,
            $shifts,
            $strat,
            $avoidFemaleNight,
            $avoidHasChild,
            $prioritizeLowBaseSalary,
            $simulateOnly,
            &$employeeStats,
            &$scheduledCount,
            &$nightShiftsCount,
            &$restDaysCount,
            &$violationsPreventedCount,
            &$totalNightAllowance,
            &$rosterPreview
        ) {
            // Duyệt từng ngày
            foreach ($period as $dt) {
                $dateStr = $dt->toDateString();

                // Sắp xếp nhân viên dựa trên chiến lược và tiêu chí
                $sortedEmployees = $employees;
                if ($strat === RosteringStrategy::FAIRNESS) {
                    $sortedEmployees = $employees->sortBy(function ($emp) use ($employeeStats) {
                        return $employeeStats[$emp->id]['night_shifts_count'];
                    });
                } elseif ($strat === RosteringStrategy::COST_OPTIMIZATION || $prioritizeLowBaseSalary) {
                    // Ưu tiên nhân viên có mức lương cơ sở thấp hơn xếp trước ca đêm
                    $sortedEmployees = $employees->sortBy(function ($emp) use ($employeeStats) {
                        return $employeeStats[$emp->id]['base_salary'];
                    });
                }

                foreach ($sortedEmployees as $emp) {
                    $empId = $emp->id;

                    // 1. Kiểm tra quy tắc nghỉ tuần: Nếu đã làm việc 6 ngày liên tục, bắt buộc ngày thứ 7 là ngày nghỉ (HC-02)
                    if ($employeeStats[$empId]['consecutive_work_days'] >= 6) {
                        if (!$simulateOnly) {
                            $this->assignRestDay($tenantId, $empId, $dateStr);
                        }
                        $employeeStats[$empId]['consecutive_work_days'] = 0;
                        $employeeStats[$empId]['rest_days_count']++;
                        $restDaysCount++;
                        continue;
                    }

                    // 2. Thử từng ca làm việc để tìm ca phù hợp
                    $assignedShift = null;

                    $candidateShifts = $shifts;
                    if ($strat === RosteringStrategy::COST_OPTIMIZATION) {
                        // Ưu tiên ca ngày thường trước
                        $candidateShifts = $shifts->sortBy('is_night_shift');
                    }

                    foreach ($candidateShifts as $shift) {
                        // Tiêu chí nâng cao: Tránh ca đêm cho nữ nếu được yêu cầu
                        if ($shift->is_night_shift && $avoidFemaleNight && $emp->gender === 'FEMALE') {
                            continue;
                        }

                        // Tiêu chí nâng cao: Tránh ca đêm cho người có con nhỏ nếu được yêu cầu
                        if ($shift->is_night_shift && $avoidHasChild) {
                            $hasActiveChild = $emp->children && $emp->children->count() > 0;
                            if ($hasActiveChild) {
                                continue;
                            }
                        }

                        // Kiểm tra ràng buộc cứng BLLĐ 2019 (12h giữa ca, phụ nữ có thai từ tháng 7, con dưới 12 tháng)
                        $check = $this->validator->validateShiftAssignment($empId, $shift->id, $dateStr);

                        if ($check['is_hard_violation']) {
                            $violationsPreventedCount++;
                            continue;
                        }

                        $assignedShift = $shift;
                        break;
                    }

                    // Nếu không chọn được ca và có bộ lọc khắt khe, thử lại lần 2 chỉ với ca ngày
                    if (!$assignedShift) {
                        $dayOnlyShifts = $shifts->where('is_night_shift', false);
                        foreach ($dayOnlyShifts as $dayShift) {
                            $check = $this->validator->validateShiftAssignment($empId, $dayShift->id, $dateStr);
                            if (!$check['is_hard_violation']) {
                                $assignedShift = $dayShift;
                                break;
                            } else {
                                $violationsPreventedCount++;
                            }
                        }
                    }

                    if ($assignedShift) {
                        if (!$simulateOnly) {
                            Roster::updateOrCreate(
                                [
                                    'tenant_id' => $tenantId,
                                    'employee_id' => $empId,
                                    'roster_date' => $dateStr,
                                ],
                                [
                                    'shift_id' => $assignedShift->id,
                                    'status' => 'SCHEDULED',
                                    'violation_flags' => [],
                                ]
                            );
                        }

                        $scheduledCount++;
                        $employeeStats[$empId]['consecutive_work_days']++;
                        $employeeStats[$empId]['total_hours'] += (float)$assignedShift->work_hours;

                        if ($assignedShift->is_night_shift) {
                            $nightShiftsCount++;
                            $employeeStats[$empId]['night_shifts_count']++;

                            // Tính phụ cấp ca đêm (Điều 98 BLLĐ)
                            $nightRate = (float)($assignedShift->night_allowance_rate ?? 30.00);
                            $nightBonus = (float)($assignedShift->night_bonus_amount ?? 0.00);
                            $allowance = ((float)$assignedShift->work_hours * $employeeStats[$empId]['hourly_rate'] * ($nightRate / 100.0)) + $nightBonus;

                            $employeeStats[$empId]['estimated_night_allowance'] += $allowance;
                            $totalNightAllowance += $allowance;
                        } else {
                            $employeeStats[$empId]['day_shifts_count']++;
                        }

                        $rosterPreview[] = [
                            'date' => $dateStr,
                            'employee_id' => $empId,
                            'user_code' => $emp->user_code,
                            'full_name' => $emp->full_name,
                            'shift_id' => $assignedShift->id,
                            'shift_code' => $assignedShift->code,
                            'shift_name' => $assignedShift->name,
                            'is_night_shift' => (bool)$assignedShift->is_night_shift,
                        ];
                    } else {
                        // Không tìm được ca phù hợp mà không vi phạm luật -> Cho nghỉ
                        if (!$simulateOnly) {
                            $this->assignRestDay($tenantId, $empId, $dateStr);
                        }
                        $employeeStats[$empId]['consecutive_work_days'] = 0;
                        $employeeStats[$empId]['rest_days_count']++;
                        $restDaysCount++;
                    }
                }
            }
        };

        if ($simulateOnly) {
            $executionCallback();
        } else {
            DB::transaction($executionCallback);
        }

        // Tạo mảng đối soát từng nhân viên
        $breakdown = [];
        foreach ($employeeStats as $eId => $stat) {
            $emp = $stat['employee'];
            $breakdown[] = [
                'employee_id' => $eId,
                'user_code' => $emp->user_code,
                'full_name' => $emp->full_name,
                'gender' => $emp->gender,
                'status' => $emp->status,
                'has_children' => $emp->children && $emp->children->count() > 0,
                'base_salary' => $stat['base_salary'],
                'night_shifts_count' => $stat['night_shifts_count'],
                'day_shifts_count' => $stat['day_shifts_count'],
                'rest_days_count' => $stat['rest_days_count'],
                'total_hours' => $stat['total_hours'],
                'estimated_night_allowance' => round($stat['estimated_night_allowance'], 0),
            ];
        }

        return [
            'total_shifts_scheduled' => $scheduledCount,
            'total_night_shifts' => $nightShiftsCount,
            'total_rest_days' => $restDaysCount,
            'employees_count' => $employees->count(),
            'violations_prevented' => $violationsPreventedCount,
            'estimated_night_allowance' => round($totalNightAllowance, 0),
            'employee_breakdown' => $breakdown,
            'roster_preview' => $rosterPreview,
            'is_simulated' => $simulateOnly,
        ];
    }

    protected function assignRestDay(int $tenantId, int $employeeId, string $dateStr): void
    {
        Roster::where('tenant_id', $tenantId)
            ->where('employee_id', $employeeId)
            ->whereDate('roster_date', $dateStr)
            ->delete();
    }
}
