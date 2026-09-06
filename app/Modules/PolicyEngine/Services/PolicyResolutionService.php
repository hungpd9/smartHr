<?php

declare(strict_types=1);

namespace App\Modules\PolicyEngine\Services;

use App\Modules\PolicyEngine\Models\PolicyVersion;
use App\Modules\PolicyEngine\Models\AttendancePolicyConfig;
use App\Modules\PolicyEngine\Models\PayrollPolicyConfig;
use App\Modules\PolicyEngine\Models\ChildWelfarePolicy;
use App\Modules\PolicyEngine\Models\WelfareScheme;
use App\Modules\PolicyEngine\Models\PitTaxBracket;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Collection;

class PolicyResolutionService
{
    /**
     * Tìm phiên bản quy chế áp dụng tại một mốc thời gian cụ thể (Effective Dating)
     * Tích hợp Caching đa tầng với cơ chế Self-Healing bảo đảm an toàn khi unserialize
     */
    public function resolveForDate(int $tenantId, Carbon $targetDate): ?PolicyVersion
    {
        // 1. Nạp trước các Model vào PHP runtime để bảo đảm unserialize an toàn
        class_exists(PolicyVersion::class);
        class_exists(AttendancePolicyConfig::class);
        class_exists(PayrollPolicyConfig::class);
        class_exists(ChildWelfarePolicy::class);
        class_exists(WelfareScheme::class);
        class_exists(PitTaxBracket::class);

        $dateStr = $targetDate->format('Y-m-d');
        $cacheKey = "smart_hr:policy_version:{$tenantId}:{$dateStr}";
        $knownDatesKey = "smart_hr:policy_known_dates:{$tenantId}";

        // 2. Kiểm tra Cache hiện hành và tự động dọn sạch nếu dữ liệu bị lỗi __PHP_Incomplete_Class
        $cached = Cache::get($cacheKey);
        if ($cached instanceof \__PHP_Incomplete_Class || ($cached !== null && !($cached instanceof PolicyVersion))) {
            Cache::forget($cacheKey);
            $cached = null;
        }

        if ($cached instanceof PolicyVersion) {
            return $cached;
        }

        // 3. Ghi nhận mốc ngày vào index để phục vụ cache invalidation
        $dates = Cache::get($knownDatesKey, []);
        if (!in_array($dateStr, $dates, true)) {
            $dates[] = $dateStr;
            Cache::put($knownDatesKey, $dates, 86400 * 7);
        }

        // 4. Truy vấn CSDL và nạp đầy đủ các cấu hình quan hệ
        $version = PolicyVersion::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('effective_from', '<=', $dateStr)
            ->where(function ($query) use ($dateStr) {
                $query->whereNull('effective_to')
                      ->orWhere('effective_to', '>=', $dateStr);
            })
            ->with(['attendanceConfig', 'payrollConfig', 'childWelfarePolicies', 'welfareSchemes', 'pitTaxBrackets'])
            ->latest('effective_from')
            ->first();

        if ($version) {
            Cache::put($cacheKey, $version, 86400);
        }

        return $version;
    }

    /**
     * Xóa sạch Cache Quy chế khi có thay đổi cấu hình (Cache Invalidation)
     */
    public function clearPolicyCache(int $tenantId): void
    {
        $knownDatesKey = "smart_hr:policy_known_dates:{$tenantId}";
        $dates = Cache::get($knownDatesKey, []);

        foreach ($dates as $dateStr) {
            Cache::forget("smart_hr:policy_version:{$tenantId}:{$dateStr}");
        }

        Cache::forget($knownDatesKey);
    }

    /**
     * Lấy cấu hình chấm công & nấc phạt tại mốc ngày xét
     */
    public function resolveAttendanceConfig(int $tenantId, Carbon $targetDate): ?AttendancePolicyConfig
    {
        $version = $this->resolveForDate($tenantId, $targetDate);
        return $version?->attendanceConfig;
    }

    /**
     * Lấy cấu hình lương, thuế & bảo hiểm tại mốc ngày xét
     */
    public function resolvePayrollConfig(int $tenantId, Carbon $targetDate): ?\App\Modules\PolicyEngine\Models\PayrollPolicyConfig
    {
        $version = $this->resolveForDate($tenantId, $targetDate);
        return $version?->payrollConfig;
    }

    /**
     * Tìm chính sách phúc lợi con nhỏ phù hợp với số tháng tuổi của con
     */
    public function resolveChildWelfarePolicy(int $tenantId, Carbon $targetDate, int $childAgeMonths): ?ChildWelfarePolicy
    {
        $version = $this->resolveForDate($tenantId, $targetDate);
        if (!$version) {
            return null;
        }

        return $version->childWelfarePolicies
            ->where('is_active', true)
            ->first(function (ChildWelfarePolicy $policy) use ($childAgeMonths) {
                return $childAgeMonths >= $policy->min_age_months && $childAgeMonths <= $policy->max_age_months;
            });
    }

    /**
     * Tính tiền phạt đi muộn / về sớm dựa trên nấc phút và hệ số tần suất vi phạm trong tháng
     */
    public function calculateLatePenalty(
        int $tenantId,
        Carbon $targetDate,
        int $lateMinutes,
        int $violationCountThisMonth = 1
    ): array {
        $config = $this->resolveAttendanceConfig($tenantId, $targetDate);

        if (!$config) {
            return [
                'late_minutes' => $lateMinutes,
                'grace_period' => 0,
                'is_exempted' => false,
                'base_fine' => 0.0,
                'multiplier' => 1.0,
                'final_fine' => 0.0,
                'reason' => 'Không tìm thấy cấu hình quy chế chấm công hiệu lực',
            ];
        }

        // 1. Kiểm tra thời gian miễn trừ (Grace Period)
        $gracePeriod = $config->grace_period_late_minutes;
        if ($lateMinutes <= $gracePeriod) {
            return [
                'late_minutes' => $lateMinutes,
                'grace_period' => $gracePeriod,
                'is_exempted' => true,
                'base_fine' => 0.0,
                'multiplier' => 1.0,
                'final_fine' => 0.0,
                'reason' => "Trong khoảng miễn trừ ({$gracePeriod} phút)",
            ];
        }

        // 2. Tra cứu nấc phạt theo phút (penalty_tiers_json)
        $baseFine = 0.0;
        $matchedTier = null;
        $tiers = $config->penalty_tiers_json ?? [];

        foreach ($tiers as $tier) {
            $min = (int) ($tier['min'] ?? 0);
            $max = isset($tier['max']) && $tier['max'] !== null ? (int) $tier['max'] : PHP_INT_MAX;

            if ($lateMinutes >= $min && $lateMinutes <= $max) {
                $baseFine = (float) ($tier['fine'] ?? 0);
                $matchedTier = $tier;
                break;
            }
        }

        // Nếu vượt quá bậc cao nhất, lấy mức phạt của bậc cao nhất
        if ($matchedTier === null && !empty($tiers)) {
            $lastTier = end($tiers);
            $baseFine = (float) ($lastTier['fine'] ?? 0);
            $matchedTier = $lastTier;
        }

        // 3. Nhân hệ số theo tần suất vi phạm lũy kế trong tháng (frequency_penalty_json)
        $multiplier = 1.0;
        $freqRules = $config->frequency_penalty_json ?? [];

        // Sắp xếp tần suất tăng dần
        usort($freqRules, fn($a, $b) => ($a['count'] ?? 0) <=> ($b['count'] ?? 0));

        foreach ($freqRules as $rule) {
            $countThreshold = (int) ($rule['count'] ?? 1);
            if ($violationCountThisMonth >= $countThreshold) {
                $multiplier = (float) ($rule['multiplier'] ?? 1.0);
            }
        }

        $finalFine = round($baseFine * $multiplier, 2);

        return [
            'late_minutes' => $lateMinutes,
            'grace_period' => $gracePeriod,
            'is_exempted' => false,
            'base_fine' => $baseFine,
            'multiplier' => $multiplier,
            'final_fine' => $finalFine,
            'violation_count' => $violationCountThisMonth,
            'matched_tier' => $matchedTier,
            'reason' => "Vi phạm {$lateMinutes} phút (Lần thứ {$violationCountThisMonth} trong tháng)",
        ];
    }

    /**
     * Tính thuế Thu nhập Cá nhân (PIT) theo Biểu lũy tiến 5 bậc năm 2026
     * Công thức: Thuế = (Thu nhập tính thuế * Thuế suất %) - Số trừ nhanh
     */
    public function calculatePitTax(int $tenantId, Carbon $targetDate, float $taxableIncome): array
    {
        if ($taxableIncome <= 0) {
            return [
                'taxable_income' => 0.0,
                'tier' => 0,
                'tax_rate' => 0.0,
                'quick_subtraction' => 0.0,
                'tax_amount' => 0.0,
                'effective_rate' => 0.0,
            ];
        }

        $version = $this->resolveForDate($tenantId, $targetDate);
        $brackets = $version ? $version->pitTaxBrackets : Collection::empty();

        // Nếu chưa có trong DB, fallback về biểu 5 bậc chuẩn 2026
        if ($brackets->isEmpty()) {
            return $this->calculateFallbackPitTax($taxableIncome);
        }

        $appliedBracket = null;
        foreach ($brackets as $bracket) {
            $min = (float) $bracket->min_income;
            $max = $bracket->max_income !== null ? (float) $bracket->max_income : PHP_FLOAT_MAX;

            if ($taxableIncome >= $min && $taxableIncome <= $max) {
                $appliedBracket = $bracket;
                break;
            }
        }

        if (!$appliedBracket) {
            // Lấy bậc cao nhất
            $appliedBracket = $brackets->last();
        }

        $rate = (float) $appliedBracket->tax_rate;
        $quickSub = (float) $appliedBracket->quick_subtraction;
        $taxAmount = max(0.0, round(($taxableIncome * ($rate / 100)) - $quickSub, 2));
        $effectiveRate = round(($taxAmount / $taxableIncome) * 100, 2);

        return [
            'taxable_income' => $taxableIncome,
            'tier' => (int) $appliedBracket->tier,
            'tax_rate' => $rate,
            'quick_subtraction' => $quickSub,
            'tax_amount' => $taxAmount,
            'effective_rate' => $effectiveRate,
        ];
    }

    /**
     * Biểu thuế TNCN 5 bậc dự phòng chuẩn luật 2026
     */
    private function calculateFallbackPitTax(float $taxableIncome): array
    {
        // Bậc 1: Đến 10 triệu -> 5%, trừ 0
        // Bậc 2: Trên 10 tr đến 30 tr -> 10%, trừ 500.000
        // Bậc 3: Trên 30 tr đến 60 tr -> 20%, trừ 3.500.000
        // Bậc 4: Trên 60 tr đến 100 tr -> 30%, trừ 9.500.000
        // Bậc 5: Trên 100 triệu -> 35%, trừ 14.500.000
        if ($taxableIncome <= 10000000) {
            $tier = 1; $rate = 5.0; $sub = 0.0;
        } elseif ($taxableIncome <= 30000000) {
            $tier = 2; $rate = 10.0; $sub = 500000.0;
        } elseif ($taxableIncome <= 60000000) {
            $tier = 3; $rate = 20.0; $sub = 3500000.0;
        } elseif ($taxableIncome <= 100000000) {
            $tier = 4; $rate = 30.0; $sub = 9500000.0;
        } else {
            $tier = 5; $rate = 35.0; $sub = 14500000.0;
        }

        $taxAmount = max(0.0, round(($taxableIncome * ($rate / 100)) - $sub, 2));
        $effectiveRate = round(($taxAmount / $taxableIncome) * 100, 2);

        return [
            'taxable_income' => $taxableIncome,
            'tier' => $tier,
            'tax_rate' => $rate,
            'quick_subtraction' => $sub,
            'tax_amount' => $taxAmount,
            'effective_rate' => $effectiveRate,
        ];
    }
}
