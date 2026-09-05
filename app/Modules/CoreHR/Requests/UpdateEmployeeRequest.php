<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'in:MALE,FEMALE,OTHER'],
            'birth_date' => ['required', 'date'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'attendance_code' => ['nullable', 'string', 'max:50'],
            'citizen_id' => ['nullable', 'string', 'max:20'],
            'hometown' => ['nullable', 'string', 'max:150'],
            'permanent_address' => ['nullable', 'string', 'max:255'],
            'temporary_address' => ['nullable', 'string', 'max:255'],
            'join_date' => ['required', 'date'],
            'probation_end_date' => ['nullable', 'date'],
            'status' => ['required', 'in:PROBATION,OFFICIAL,ON_LEAVE,TERMINATED'],
            'is_pregnant' => ['nullable', 'boolean'],
            'pregnancy_start_date' => ['nullable', 'date'],
            'pregnancy_weeks_at_start' => ['nullable', 'integer', 'min:1', 'max:42'],
            'expected_due_date' => ['nullable', 'date'],
        ];
    }
}
