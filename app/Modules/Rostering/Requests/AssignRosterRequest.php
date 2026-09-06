<?php

declare(strict_types=1);

namespace App\Modules\Rostering\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignRosterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => 'required|exists:employees,id',
            'roster_date' => 'required|date',
            'shift_id' => 'nullable|exists:shifts,id', // null có nghĩa là ngày nghỉ (OFF) hoặc xóa ca
            'force' => 'nullable|boolean', // Bỏ qua cảnh báo ràng buộc mềm nếu có thẩm quyền
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.required' => 'Vui lòng chọn nhân viên cần phân ca.',
            'roster_date.required' => 'Vui lòng chọn ngày phân ca.',
        ];
    }
}
