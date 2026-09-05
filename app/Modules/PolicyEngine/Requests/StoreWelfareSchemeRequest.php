<?php

declare(strict_types=1);

namespace App\Modules\PolicyEngine\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWelfareSchemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:200',
            'category' => 'required|in:CHILD_CARE,MATERNITY_FEMALE,HEALTH_WELLNESS,WORKING_COND,FINANCIAL_SUPPORT,SPECIAL_ALLOWANCE',
            'visibility' => 'required|in:VISIBLE,HIDDEN',
            'is_statutory' => 'boolean',
            'daily_exemption_minutes' => 'nullable|integer|min:0|max:480',
            'pay_type' => 'required|in:PAID,UNPAID,PARTIAL',
            'prohibit_night_shift' => 'boolean',
            'prohibit_overtime' => 'boolean',
            'additional_leave_days_per_year' => 'nullable|numeric|min:0',
            'monthly_allowance_amount' => 'nullable|numeric|min:0',
            'one_time_bonus_amount' => 'nullable|numeric|min:0',
            'is_tax_exempt' => 'boolean',
            'applicable_gender' => 'required|in:ALL,FEMALE,MALE',
            'child_min_age_months' => 'nullable|integer|min:0',
            'child_max_age_months' => 'nullable|integer|min:0',
            'min_seniority_months' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
        ];
    }
}
