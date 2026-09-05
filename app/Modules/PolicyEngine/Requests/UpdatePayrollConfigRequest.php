<?php

declare(strict_types=1);

namespace App\Modules\PolicyEngine\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePayrollConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'standard_working_days_mode' => 'required|in:FIXED,CALENDAR_BASED',
            'fixed_standard_working_days' => 'required|numeric|min:1|max:31',
            'standard_working_hours_per_day' => 'required|numeric|min:1|max:24',
            'payment_day' => 'required|integer|min:1|max:31',
            'pay_early_on_holiday_weekend' => 'boolean',
            'personal_deduction' => 'required|numeric|min:0',
            'dependent_deduction' => 'required|numeric|min:0',
            'default_child_allowance' => 'required|numeric|min:0',
            'base_salary' => 'required|numeric|min:0',
            'max_salary_bhxh' => 'required|numeric|min:0',
            'max_salary_bhtn' => 'required|numeric|min:0',
            'company_bhxh_rate' => 'required|numeric|min:0|max:100',
            'company_bhyt_rate' => 'required|numeric|min:0|max:100',
            'company_bhtn_rate' => 'required|numeric|min:0|max:100',
            'employee_bhxh_rate' => 'required|numeric|min:0|max:100',
            'employee_bhyt_rate' => 'required|numeric|min:0|max:100',
            'employee_bhtn_rate' => 'required|numeric|min:0|max:100',
            'probation_salary_percentage' => 'required|numeric|min:0|max:100',
            'probation_pit_rate' => 'required|numeric|min:0|max:100',
            'probation_pit_threshold' => 'required|numeric|min:0',
            'ot_weekday_day_rate' => 'required|numeric|min:1',
            'ot_weekday_night_rate' => 'required|numeric|min:1',
            'ot_weekend_day_rate' => 'required|numeric|min:1',
            'ot_weekend_night_rate' => 'required|numeric|min:1',
            'ot_holiday_day_rate' => 'required|numeric|min:1',
            'ot_holiday_night_rate' => 'required|numeric|min:1',
            'ot_tax_exempt_daily_limit_percent' => 'required|integer|min:0|max:100',
            'ot_tax_exempt_monthly_limit_hours' => 'required|integer|min:0|max:200',
            'ot_tax_exempt_yearly_limit_hours' => 'required|integer|min:0|max:1000',
        ];
    }
}
