<?php

declare(strict_types=1);

namespace App\Modules\LeavePolicy\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConvertOtRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'year' => ['required', 'integer', 'min:2020', 'max:2030'],
            'ot_hours' => ['required', 'numeric', 'min:0.5', 'max:100.0'],
            'conversion_rate' => ['nullable', 'numeric', 'min:1.0', 'max:24.0'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.required' => 'Vui lòng chọn nhân viên cần quy đổi.',
            'year.required' => 'Vui lòng chọn năm áp dụng.',
            'ot_hours.required' => 'Vui lòng nhập số giờ làm thêm (OT) muốn quy đổi.',
            'ot_hours.min' => 'Số giờ làm thêm tối thiểu để quy đổi là 0.5 giờ.',
        ];
    }
}
