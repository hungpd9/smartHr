<?php

declare(strict_types=1);

namespace App\Modules\CoreHR\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDepartmentVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'mission_function' => ['nullable', 'string'],
            'manager_id' => ['nullable', 'exists:employees,id'],
            'effective_date' => ['required', 'date'],
            'change_reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
