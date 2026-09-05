<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OffboardEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'resigned_date' => ['required', 'date'],
            'resignation_reason' => ['required', 'string', 'max:255'],
            'decision_number' => ['nullable', 'string', 'max:100'],
            'decision_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
