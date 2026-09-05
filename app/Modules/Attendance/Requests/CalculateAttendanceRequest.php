<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CalculateAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'from_date.required' => 'Ngày bắt đầu tính công là bắt buộc.',
            'to_date.required' => 'Ngày kết thúc tính công là bắt buộc.',
            'to_date.after_or_equal' => 'Ngày kết thúc phải lớn hơn hoặc bằng ngày bắt đầu.',
        ];
    }
}
