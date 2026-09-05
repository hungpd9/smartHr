<?php

declare(strict_types=1);

namespace App\Modules\PolicyEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollPolicyConfig extends Model
{
    protected $table = 'payroll_policy_configs';

    protected $fillable = [
        'policy_version_id',
        'standard_working_days_mode',
        'fixed_standard_working_days',
        'standard_working_hours_per_day',
        'payment_day',
        'pay_early_on_holiday_weekend',
        'personal_deduction',
        'dependent_deduction',
        'default_child_allowance',
        'base_salary',
        'max_salary_bhxh',
        'max_salary_bhtn',
        'company_bhxh_rate',
        'company_bhyt_rate',
        'company_bhtn_rate',
        'employee_bhxh_rate',
        'employee_bhyt_rate',
        'employee_bhtn_rate',
        'probation_salary_percentage',
        'probation_pit_rate',
        'probation_pit_threshold',
        'ot_weekday_day_rate',
        'ot_weekday_night_rate',
        'ot_weekend_day_rate',
        'ot_weekend_night_rate',
        'ot_holiday_day_rate',
        'ot_holiday_night_rate',
        'ot_tax_exempt_daily_limit_percent',
        'ot_tax_exempt_monthly_limit_hours',
        'ot_tax_exempt_yearly_limit_hours',
    ];

    protected $casts = [
        'fixed_standard_working_days' => 'decimal:2',
        'standard_working_hours_per_day' => 'decimal:2',
        'payment_day' => 'integer',
        'pay_early_on_holiday_weekend' => 'boolean',
        'personal_deduction' => 'decimal:2',
        'dependent_deduction' => 'decimal:2',
        'default_child_allowance' => 'decimal:2',
        'base_salary' => 'decimal:2',
        'max_salary_bhxh' => 'decimal:2',
        'max_salary_bhtn' => 'decimal:2',
        'company_bhxh_rate' => 'decimal:2',
        'company_bhyt_rate' => 'decimal:2',
        'company_bhtn_rate' => 'decimal:2',
        'employee_bhxh_rate' => 'decimal:2',
        'employee_bhyt_rate' => 'decimal:2',
        'employee_bhtn_rate' => 'decimal:2',
        'probation_salary_percentage' => 'decimal:2',
        'probation_pit_rate' => 'decimal:2',
        'probation_pit_threshold' => 'decimal:2',
        'ot_weekday_day_rate' => 'decimal:2',
        'ot_weekday_night_rate' => 'decimal:2',
        'ot_weekend_day_rate' => 'decimal:2',
        'ot_weekend_night_rate' => 'decimal:2',
        'ot_holiday_day_rate' => 'decimal:2',
        'ot_holiday_night_rate' => 'decimal:2',
        'ot_tax_exempt_daily_limit_percent' => 'integer',
        'ot_tax_exempt_monthly_limit_hours' => 'integer',
        'ot_tax_exempt_yearly_limit_hours' => 'integer',
    ];

    public function policyVersion(): BelongsTo
    {
        return $this->belongsTo(PolicyVersion::class, 'policy_version_id');
    }
}
