<?php

declare(strict_types=1);

namespace App\Modules\PolicyEngine\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TestCalculationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:LATE_PENALTY,PIT_TAX'],
            'target_date' => ['nullable', 'date'],
            // Cho LATE_PENALTY
            'late_minutes' => ['required_if:type,LATE_PENALTY', 'nullable', 'integer', 'min:0'],
            'violation_count' => ['nullable', 'integer', 'min:1'],
            // Cho PIT_TAX
            'taxable_income' => ['required_if:type,PIT_TAX', 'nullable', 'numeric', 'min:0'],
        ];
    }
}
