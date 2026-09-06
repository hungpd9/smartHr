<?php

declare(strict_types=1);

namespace App\Modules\LeavePolicy\Services;

use App\Modules\CoreHR\Models\Employee;
use App\Modules\LeavePolicy\Models\LeaveBalance;
use App\Modules\LeavePolicy\Models\LeaveTransaction;
use App\Modules\LeavePolicy\Enums\LeaveType;
use App\Modules\LeavePolicy\Enums\LeaveTransactionType;
use App\Modules\LeavePolicy\Enums\SpecialLeaveType;
use App\Modules\PolicyEngine\Models\WelfareScheme;
use App\Modules\Compliance\Models\AuditTrail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LeaveBalanceService
{
    /**
     * Khởi tạo hoặc cập nhật tự động quỹ phép năm cho nhân viên theo quy chuẩn BLLĐ 2019
     * - Điều 113: 12 ngày chuẩn (14 ngày công việc nặng nhọc/độc hại)
     * - Điều 114: Thâm niên cứ đủ 5 năm +1 ngày phép (floor(seniority / 5))
     * - Phúc lợi doanh nghiệp: Bổ sung thêm ngày phép từ welfare_schemes
     * - Chuyển tiếp phép tồn (Carry-over) có hạn chót 31/03
     */
    public function initializeOrUpdateAnnualBalance(Employee $employee, int $year, ?Carbon $targetDate = null): LeaveBalance
    {
        $targetDate = $targetDate ?: Carbon::today();
        $employee->loadMissing(['currentTimeline.position', 'children']);

        // 1. Định mức phép chuẩn năm (Điều 113 BLLĐ)
        $positionTitle = $employee->currentTimeline?->position?->name ?? '';
        $isHazardous = (bool) preg_match('/(nặng nhọc|độc hại|hóa chất|nguy hiểm|bốc vác)/ui', $positionTitle);
        $statutoryAllocatedDays = $isHazardous ? 14.00 : 12.00;

        // 2. Tự động tính phép thâm niên theo Điều 114 BLLĐ 2019
        // Cứ đủ 5 năm làm việc tại doanh nghiệp được cộng thêm 1 ngày phép
        $seniorityAtStartOfYear = $employee->getSeniorityYearsAt(Carbon::create($year, 1, 1));
        $seniorityAtTarget = $employee->getSeniorityYearsAt($targetDate);
        $effectiveSeniorityYears = max($seniorityAtStartOfYear, $seniorityAtTarget);
        $seniorityBonusDays = (float) floor($effectiveSeniorityYears / 5);

        // 3. Tự động tính ngày phép cộng thêm từ Chế độ Phúc lợi Doanh nghiệp (welfare_schemes)
        $welfareBonusDays = 0.00;
        $activeWelfareSchemes = WelfareScheme::where('tenant_id', $employee->tenant_id)
            ->where('visibility', 'VISIBLE')
            ->where('additional_leave_days_per_year', '>', 0)
            ->get();

        foreach ($activeWelfareSchemes as $scheme) {
            if ($this->isEmployeeEligibleForWelfareScheme($employee, $scheme, $targetDate)) {
                $welfareBonusDays += (float) $scheme->additional_leave_days_per_year;
            }
        }

        // 4. Kiểm tra và bảo lưu phép tồn năm trước (Carry-over)
        $carriedOverDays = 0.00;
        $carriedOverExpiryDate = Carbon::create($year, 3, 31)->toDateString();

        $previousBalance = LeaveBalance::where('tenant_id', $employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->where('year', $year - 1)
            ->where('leave_type', LeaveType::ANNUAL->value)
            ->first();

        if ($previousBalance && (float) $previousBalance->remaining_days > 0) {
            $carriedOverDays = (float) $previousBalance->remaining_days;
        }

        // 5. Tính số ngày phép đã tích lũy lũy kế tính đến tháng hiện tại
        $totalEntitled = $statutoryAllocatedDays + $seniorityBonusDays + $welfareBonusDays;
        $accruedDays = 0.00;

        if ($employee->join_date) {
            if ($employee->join_date->year < $year) {
                $currentMonth = ($targetDate->year === $year) ? $targetDate->month : 12;
                $accruedDays = round(($totalEntitled / 12) * $currentMonth, 2);
            } elseif ($employee->join_date->year === $year) {
                $monthsWorked = max(1, $targetDate->month - $employee->join_date->month + 1);
                $accruedDays = round(($totalEntitled / 12) * $monthsWorked, 2);
            }
        } else {
            $accruedDays = $totalEntitled;
        }

        return DB::transaction(function () use (
            $employee,
            $year,
            $statutoryAllocatedDays,
            $seniorityBonusDays,
            $welfareBonusDays,
            $totalEntitled,
            $carriedOverDays,
            $carriedOverExpiryDate,
            $accruedDays
        ) {
            $balance = LeaveBalance::firstOrNew([
                'tenant_id' => $employee->tenant_id,
                'employee_id' => $employee->id,
                'year' => $year,
                'leave_type' => LeaveType::ANNUAL->value,
            ]);

            $isNew = !$balance->exists;

            $balance->statutory_allocated_days = $statutoryAllocatedDays;
            $balance->seniority_bonus_days = $seniorityBonusDays;
            $balance->welfare_bonus_days = $welfareBonusDays;
            $balance->total_entitled_days = $totalEntitled;
            $balance->carried_over_days = $carriedOverDays;
            $balance->carried_over_expiry_date = $carriedOverExpiryDate;
            $balance->accrued_days_to_date = min($totalEntitled, $accruedDays);

            if ($isNew) {
                $balance->used_days = 0.00;
                $balance->pending_days = 0.00;
                $balance->remaining_days = $totalEntitled + $carriedOverDays;
            } else {
                $balance->remaining_days = max(
                    0.0,
                    $totalEntitled + $balance->carried_over_days - (float) $balance->used_days - (float) $balance->pending_days
                );
            }

            // Đồng bộ các trường tương thích
            $balance->annual_entitlement = $balance->total_entitled_days;
            $balance->carried_forward_days = $balance->carried_over_days;
            $balance->taken_paid_days = $balance->used_days;

            $balance->save();

            // Nếu tạo mới, ghi nhận giao dịch tích lũy ban đầu
            if ($isNew) {
                LeaveTransaction::create([
                    'leave_balance_id' => $balance->id,
                    'transaction_type' => LeaveTransactionType::ACCRUAL->value,
                    'days' => $totalEntitled,
                    'reference_id' => null,
                    'description' => "Khởi tạo định mức phép năm {$year}: Chuẩn ({$statutoryAllocatedDays}d) + Thâm niên (+{$seniorityBonusDays}d) + Phúc lợi (+{$welfareBonusDays}d)",
                    'created_at' => now(),
                ]);

                if ($carriedOverDays > 0) {
                    LeaveTransaction::create([
                        'leave_balance_id' => $balance->id,
                        'transaction_type' => LeaveTransactionType::ACCRUAL->value,
                        'days' => $carriedOverDays,
                        'reference_id' => null,
                        'description' => "Chuyển tiếp phép tồn từ năm " . ($year - 1) . " (Hạn dùng: 31/03/{$year})",
                        'created_at' => now(),
                    ]);
                }
            }

            return $balance;
        });
    }

    /**
     * Điều chỉnh quỹ phép thủ công (Adjustment) có lưu vết kiểm toán và lịch sử giao dịch
     */
    public function adjustBalance(
        int $leaveBalanceId,
        float $days,
        string $transactionType,
        string $description,
        ?int $actorId = null
    ): LeaveBalance {
        return DB::transaction(function () use ($leaveBalanceId, $days, $transactionType, $description, $actorId) {
            $balance = LeaveBalance::findOrFail($leaveBalanceId);
            $oldRemaining = (float) $balance->remaining_days;

            $balance->remaining_days = max(0.0, (float) $balance->remaining_days + $days);
            if ($days > 0) {
                $balance->total_entitled_days = (float) $balance->total_entitled_days + $days;
                $balance->annual_entitlement = $balance->total_entitled_days;
            }
            $balance->save();

            // Ghi nhận biến động
            LeaveTransaction::create([
                'leave_balance_id' => $balance->id,
                'transaction_type' => $transactionType,
                'days' => $days,
                'description' => $description,
                'created_at' => now(),
            ]);

            // Ghi vết kiểm toán bất biến
            AuditTrail::create([
                'tenant_id' => $balance->tenant_id,
                'actor_id' => $actorId,
                'actor_type' => 'EMPLOYEE',
                'action_type' => 'ADJUST_LEAVE',
                'auditable_type' => LeaveBalance::class,
                'auditable_id' => $balance->id,
                'old_values' => ['remaining_days' => $oldRemaining],
                'new_values' => [
                    'remaining_days' => $balance->remaining_days,
                    'delta_days' => $days,
                    'transaction_type' => $transactionType,
                    'reason' => $description,
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'CLI/System',
                'created_at' => now(),
            ]);

            return $balance;
        });
    }

    /**
     * Quy đổi số giờ làm thêm (OT) sang ngày nghỉ bù (Comp-off Balance Engine)
     */
    public function convertOtToCompOff(
        Employee $employee,
        int $year,
        float $otHours,
        float $conversionRate = 8.0,
        ?string $description = null,
        ?int $actorId = null
    ): LeaveBalance {
        if ($conversionRate <= 0) {
            $conversionRate = 8.0;
        }

        $compOffDays = round($otHours / $conversionRate, 2);

        return DB::transaction(function () use ($employee, $year, $otHours, $compOffDays, $conversionRate, $description, $actorId) {
            $balance = LeaveBalance::firstOrNew([
                'tenant_id' => $employee->tenant_id,
                'employee_id' => $employee->id,
                'year' => $year,
                'leave_type' => LeaveType::COMPENSATION->value,
            ]);

            $isNew = !$balance->exists;
            $oldRemaining = (float) ($balance->remaining_days ?? 0.0);

            $balance->statutory_allocated_days = (float) ($balance->statutory_allocated_days ?? 0.0) + $compOffDays;
            $balance->total_entitled_days = (float) ($balance->total_entitled_days ?? 0.0) + $compOffDays;
            $balance->remaining_days = (float) ($balance->remaining_days ?? 0.0) + $compOffDays;
            $balance->comp_off_hours = (float) ($balance->comp_off_hours ?? 0.0) + $otHours;

            if ($isNew) {
                $balance->used_days = 0.00;
                $balance->pending_days = 0.00;
                $balance->carried_over_days = 0.00;
                $balance->seniority_bonus_days = 0.00;
                $balance->welfare_bonus_days = 0.00;
                $balance->accrued_days_to_date = $compOffDays;
            }

            $balance->save();

            $desc = $description ?: "Quy đổi {$otHours}h làm thêm giờ sang {$compOffDays} ngày nghỉ bù (Tỷ lệ {$conversionRate}h/ngày)";

            LeaveTransaction::create([
                'leave_balance_id' => $balance->id,
                'transaction_type' => LeaveTransactionType::COMPENSATION_CONVERT->value,
                'days' => $compOffDays,
                'description' => $desc,
                'created_at' => now(),
            ]);

            AuditTrail::create([
                'tenant_id' => $balance->tenant_id,
                'actor_id' => $actorId,
                'actor_type' => 'EMPLOYEE',
                'action_type' => 'CONVERT_OT_TO_COMPOFF',
                'auditable_type' => LeaveBalance::class,
                'auditable_id' => $balance->id,
                'old_values' => ['remaining_days' => $oldRemaining],
                'new_values' => [
                    'remaining_days' => $balance->remaining_days,
                    'ot_hours_converted' => $otHours,
                    'comp_off_days' => $compOffDays,
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'System',
                'created_at' => now(),
            ]);

            return $balance;
        });
    }

    /**
     * Tự động xử lý hết hạn phép tồn sau ngày 31/03 (Carry-over Expiration)
     */
    public function expireCarriedOverBalances(int $year, ?Carbon $currentDate = null): int
    {
        $currentDate = $currentDate ?: Carbon::today();

        $expiredBalances = LeaveBalance::where('year', $year)
            ->where('leave_type', LeaveType::ANNUAL->value)
            ->where('carried_over_days', '>', 0)
            ->whereNotNull('carried_over_expiry_date')
            ->whereDate('carried_over_expiry_date', '<', $currentDate)
            ->get();

        $count = 0;

        foreach ($expiredBalances as $balance) {
            DB::transaction(function () use ($balance, &$count) {
                // Phép tồn được sử dụng ưu tiên trước. Phần tồn chưa dùng = max(0, carried_over - used_days)
                $unusedCarriedOver = max(0.0, (float) $balance->carried_over_days - (float) $balance->used_days);

                if ($unusedCarriedOver > 0) {
                    $balance->remaining_days = max(0.0, (float) $balance->remaining_days - $unusedCarriedOver);
                    $balance->carried_over_days = 0.00;
                    $balance->carried_forward_days = 0.00;
                    $balance->save();

                    LeaveTransaction::create([
                        'leave_balance_id' => $balance->id,
                        'transaction_type' => LeaveTransactionType::EXPIRATION->value,
                        'days' => -$unusedCarriedOver,
                        'description' => "Hết hạn sử dụng phép tồn năm trước ({$unusedCarriedOver} ngày) sau ngày {$balance->carried_over_expiry_date}",
                        'created_at' => now(),
                    ]);

                    $count++;
                }
            });
        }

        return $count;
    }

    /**
     * Kiểm tra nhân viên có đủ điều kiện hưởng chế độ phúc lợi hay không
     */
    protected function isEmployeeEligibleForWelfareScheme(Employee $employee, WelfareScheme $scheme, Carbon $targetDate): bool
    {
        // 1. Kiểm tra giới tính
        if ($scheme->applicable_gender !== 'ALL' && $scheme->applicable_gender !== $employee->gender) {
            return false;
        }

        // 2. Kiểm tra thâm niên tối thiểu
        if ($scheme->min_seniority_months > 0) {
            if (!$employee->join_date) {
                return false;
            }
            $seniorityMonths = (int) $employee->join_date->diffInMonths($targetDate);
            if ($seniorityMonths < $scheme->min_seniority_months) {
                return false;
            }
        }

        // 3. Kiểm tra độ tuổi con nhỏ (nếu có yêu cầu con nhỏ)
        if ($scheme->child_min_age_months !== null || $scheme->child_max_age_months !== null) {
            $hasMatchingChild = $employee->children->contains(function ($child) use ($scheme, $targetDate) {
                if (!$child->is_active || !$child->birth_date) {
                    return false;
                }
                $ageMonths = (int) Carbon::parse($child->birth_date)->diffInMonths($targetDate);
                $minPass = $scheme->child_min_age_months === null || $ageMonths >= $scheme->child_min_age_months;
                $maxPass = $scheme->child_max_age_months === null || $ageMonths <= $scheme->child_max_age_months;
                return $minPass && $maxPass;
            });

            if (!$hasMatchingChild) {
                return false;
            }
        }

        return true;
    }

    /**
     * Lấy số ngày nghỉ việc riêng có hưởng 100% lương theo Điều 115 BLLĐ 2019
     */
    public function getSpecialLeaveStatutoryDays(SpecialLeaveType|string $type): float
    {
        if (is_string($type)) {
            $type = SpecialLeaveType::from($type);
        }

        return $type->statutoryDays();
    }
}
