<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCareerTimelineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_type' => ['required', 'in:ONBOARDING,TRANSFER,PROMOTION,ROLE_CHANGE,SALARY_ADJUSTMENT,OFFBOARDING'],
            'department_id' => ['required', 'exists:departments,id'],
            'position_id' => ['required', 'exists:positions,id'],
            'job_title' => ['required', 'string', 'max:150'],
            'manager_id' => ['nullable', 'exists:employees,id'],
            'effective_date' => ['required', 'date'],
            'decision_number' => ['nullable', 'string', 'max:100'],
            'decision_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
