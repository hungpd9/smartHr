<?php

declare(strict_types=1);

namespace App\Modules\PolicyEngine\Services;

use App\Modules\PolicyEngine\Models\PolicyVersion;
use App\Modules\PolicyEngine\Models\AttendancePolicyConfig;
use App\Modules\PolicyEngine\Models\PayrollPolicyConfig;
use App\Modules\PolicyEngine\Models\ChildWelfarePolicy;
use App\Modules\PolicyEngine\Models\PitTaxBracket;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PolicyVersionService
{
    public function __construct(
        protected PolicyResolutionService $resolutionService
    ) {}

    /**
     * Tạo phiên bản quy chế mới với tính năng Effective Dating (Đóng phiên bản cũ, mở phiên bản mới)
     */
    public function createVersion(int $tenantId, array $data, ?int $cloneFromVersionId = null): PolicyVersion
    {
        $version = DB::transaction(function () use ($tenantId, $data, $cloneFromVersionId) {
            $effectiveFrom = Carbon::parse($data['effective_from']);

            // 1. Nếu có phiên bản đang hiệu lực trước đó có effective_to IS NULL, cập nhật effective_to = effective_from - 1 ngày
            $previousActive = PolicyVersion::where('tenant_id', $tenantId)
                ->where('effective_from', '<', $effectiveFrom->format('Y-m-d'))
                ->where(function ($q) use ($effectiveFrom) {
                    $q->whereNull('effective_to')
                      ->orWhere('effective_to', '>=', $effectiveFrom->format('Y-m-d'));
                })
                ->orderByDesc('effective_from')
                ->first();

            if ($previousActive) {
                $previousActive->update([
                    'effective_to' => $effectiveFrom->copy()->subDay()->format('Y-m-d'),
                ]);
            }

            // 2. Tạo PolicyVersion mới
            $version = PolicyVersion::create([
                'tenant_id' => $tenantId,
                'code' => $data['code'],
                'name' => $data['name'],
                'effective_from' => $effectiveFrom->format('Y-m-d'),
                'effective_to' => isset($data['effective_to']) && $data['effective_to'] ? Carbon::parse($data['effective_to'])->format('Y-m-d') : null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            // 3. Nhân bản cấu hình từ phiên bản chỉ định hoặc tạo mặc định chuẩn BLLĐ 2019
            if ($cloneFromVersionId) {
                $sourceVersion = PolicyVersion::with(['attendanceConfig', 'payrollConfig', 'childWelfarePolicies', 'pitTaxBrackets'])
                    ->findOrFail($cloneFromVersionId);

                if ($sourceVersion->attendanceConfig) {
                    $configData = $sourceVersion->attendanceConfig->toArray();
                    unset($configData['id'], $configData['policy_version_id'], $configData['created_at'], $configData['updated_at']);
                    $version->attendanceConfig()->create($configData);
                }

                if ($sourceVersion->payrollConfig) {
                    $payrollData = $sourceVersion->payrollConfig->toArray();
                    unset($payrollData['id'], $payrollData['policy_version_id'], $payrollData['created_at'], $payrollData['updated_at']);
                    $version->payrollConfig()->create($payrollData);
                } else {
                    $this->initDefaultPayrollConfig($version);
                }

                foreach ($sourceVersion->childWelfarePolicies as $cwp) {
                    $cwpData = $cwp->toArray();
                    unset($cwpData['id'], $cwpData['policy_version_id'], $cwpData['created_at'], $cwpData['updated_at']);
                    $version->childWelfarePolicies()->create($cwpData);
                }

                foreach ($sourceVersion->pitTaxBrackets as $bracket) {
                    $bData = $bracket->toArray();
                    unset($bData['id'], $bData['policy_version_id'], $bData['created_at'], $bData['updated_at']);
                    $version->pitTaxBrackets()->create($bData);
                }
            } else {
                // Khởi tạo các cấu hình mặc định luật lao động
                $this->initDefaultConfigs($version);
            }

            return $version->load(['attendanceConfig', 'payrollConfig', 'childWelfarePolicies', 'pitTaxBrackets']);
        });

        $this->resolutionService->clearPolicyCache($tenantId);

        return $version;
    }

    /**
     * Khởi tạo các bảng cấu hình mặc định tuân thủ BLLĐ 2019 & Biểu thuế 2026
     */
    public function initDefaultConfigs(PolicyVersion $version): void
    {
        // Cấu hình chấm công mặc định
        $version->attendanceConfig()->create([
            'grace_period_late_minutes' => 5,
            'grace_period_early_minutes' => 5,
            'leave_unit_hours' => 4.00,
            'early_leave_unit_hours' => 0.25,
            'min_rest_hours_between_shifts' => 12,
            'min_weekly_rest_hours' => 24,
            'max_weekly_standard_hours' => 48,
            'ot_to_rostered_rate' => 1.00,
            'compensation_rate' => 8.00,
            'penalty_tiers_json' => [
                ['min' => 1, 'max' => 15, 'fine' => 50000],
                ['min' => 16, 'max' => 30, 'fine' => 100000],
                ['min' => 31, 'max' => 60, 'fine' => 200000],
            ],
            'frequency_penalty_json' => [
                ['count' => 1, 'multiplier' => 1.0],
                ['count' => 3, 'multiplier' => 1.5],
                ['count' => 5, 'multiplier' => 2.0],
            ],
        ]);

        // Cấu hình lương mặc định
        $this->initDefaultPayrollConfig($version);

        // Phúc lợi con nhỏ mặc định: Điều 137 BLLĐ
        $version->childWelfarePolicies()->createMany([
            [
                'name' => 'Chế độ Lao động Nuôi con Dưới 12 Tháng (Bắt buộc theo Luật)',
                'min_age_months' => 0,
                'max_age_months' => 12,
                'daily_exemption_minutes' => 60,
                'pay_type' => 'PAID',
                'prohibit_night_shift' => true,
                'prohibit_overtime' => true,
                'is_statutory' => true,
                'is_active' => true,
                'visibility' => 'VISIBLE',
            ],
            [
                'name' => 'Phúc lợi Doanh nghiệp: Hỗ trợ Con 1 - 11 tuổi',
                'min_age_months' => 13,
                'max_age_months' => 132,
                'daily_exemption_minutes' => 30,
                'pay_type' => 'PAID',
                'prohibit_night_shift' => false,
                'prohibit_overtime' => false,
                'is_statutory' => false,
                'is_active' => true,
                'visibility' => 'VISIBLE',
            ]
        ]);

        // Biểu thuế TNCN 5 bậc hiệu lực từ 2026
        $version->pitTaxBrackets()->createMany([
            ['tier' => 1, 'min_income' => 0.00, 'max_income' => 10000000.00, 'tax_rate' => 5.00, 'quick_subtraction' => 0.00],
            ['tier' => 2, 'min_income' => 10000000.01, 'max_income' => 30000000.00, 'tax_rate' => 10.00, 'quick_subtraction' => 500000.00],
            ['tier' => 3, 'min_income' => 30000000.01, 'max_income' => 60000000.00, 'tax_rate' => 20.00, 'quick_subtraction' => 3500000.00],
            ['tier' => 4, 'min_income' => 60000000.01, 'max_income' => 100000000.00, 'tax_rate' => 30.00, 'quick_subtraction' => 9500000.00],
            ['tier' => 5, 'min_income' => 100000000.01, 'max_income' => null, 'tax_rate' => 35.00, 'quick_subtraction' => 14500000.00],
        ]);
    }

    /**
     * Khởi tạo cấu hình lương mặc định theo quy chuẩn lao động và bảo hiểm xã hội 2026
     */
    public function initDefaultPayrollConfig(PolicyVersion $version): PayrollPolicyConfig
    {
        return $version->payrollConfig()->create([
            'standard_working_days_mode' => 'FIXED',
            'fixed_standard_working_days' => 26.00,
            'standard_working_hours_per_day' => 8.00,
            'payment_day' => 10,
            'pay_early_on_holiday_weekend' => true,
            'personal_deduction' => 11000000.00,
            'dependent_deduction' => 4400000.00,
            'default_child_allowance' => 300000.00,
            'base_salary' => 2340000.00,
            'max_salary_bhxh' => 46800000.00,
            'max_salary_bhtn' => 99200000.00,
            'company_bhxh_rate' => 17.50,
            'company_bhyt_rate' => 3.00,
            'company_bhtn_rate' => 1.00,
            'employee_bhxh_rate' => 8.00,
            'employee_bhyt_rate' => 1.50,
            'employee_bhtn_rate' => 1.00,
            'probation_salary_percentage' => 85.00,
            'probation_pit_rate' => 10.00,
            'probation_pit_threshold' => 2000000.00,
            'ot_weekday_day_rate' => 1.50,
            'ot_weekday_night_rate' => 2.00,
            'ot_weekend_day_rate' => 2.00,
            'ot_weekend_night_rate' => 2.70,
            'ot_holiday_day_rate' => 3.00,
            'ot_holiday_night_rate' => 3.90,
            'ot_tax_exempt_daily_limit_percent' => 50,
            'ot_tax_exempt_monthly_limit_hours' => 40,
            'ot_tax_exempt_yearly_limit_hours' => 200,
        ]);
    }

    /**
     * Cập nhật cấu hình chấm công & nấc phạt
     */
    public function updateAttendanceConfig(AttendancePolicyConfig $config, array $data): AttendancePolicyConfig
    {
        $config->update($data);
        $tenantId = (int) ($config->policyVersion->tenant_id ?? 1);
        $this->resolutionService->clearPolicyCache($tenantId);
        return $config->fresh();
    }

    /**
     * Cập nhật cấu hình lương, thuế & bảo hiểm
     */
    public function updatePayrollConfig(PayrollPolicyConfig $config, array $data): PayrollPolicyConfig
    {
        $config->update($data);
        $tenantId = (int) ($config->policyVersion->tenant_id ?? 1);
        $this->resolutionService->clearPolicyCache($tenantId);
        return $config->fresh();
    }
}
