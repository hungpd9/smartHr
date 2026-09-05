<?php

declare(strict_types=1);

namespace App\Modules\PolicyEngine\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAllowanceRequest extends FormRequest
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
            'apply_type' => 'required|in:COMPANY_FIXED,INDIVIDUAL_FIXED,IRREGULAR',
            'is_insurance_taxable' => 'boolean',
            'is_pit_taxable' => 'boolean',
            'is_included_in_ot_wage' => 'boolean',
            'tax_exemption_limit' => 'nullable|numeric|min:0',
            'default_amount' => 'nullable|numeric|min:0',
            'visibility' => 'required|in:VISIBLE,HIDDEN',
            'notes' => 'nullable|string',
        ];
    }
}
