<?php

declare(strict_types=1);

namespace App\Modules\PolicyEngine\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChildWelfarePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'min_age_months' => ['required', 'integer', 'min:0'],
            'max_age_months' => ['required', 'integer', 'gt:min_age_months'],
            'daily_exemption_minutes' => ['required', 'integer', 'min:0', 'max:120'],
            'pay_type' => ['required', 'in:PAID,UNPAID,PARTIAL'],
            'prohibit_night_shift' => ['boolean'],
            'prohibit_overtime' => ['boolean'],
            'is_statutory' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}
