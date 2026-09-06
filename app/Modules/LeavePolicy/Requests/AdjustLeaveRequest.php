<?php

declare(strict_types=1);

namespace App\Modules\LeavePolicy\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdjustLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'leave_balance_id' => ['required', 'integer', 'exists:leave_balances,id'],
            'days' => ['required', 'numeric', 'not_in:0'],
            'transaction_type' => ['required', 'string', 'in:ACCRUAL,USAGE,EXPIRATION,ADJUSTMENT'],
            'description' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'leave_balance_id.required' => 'Vui lòng chọn bản ghi quỹ phép cần điều chỉnh.',
            'days.required' => 'Vui lòng nhập số ngày biến động.',
            'days.not_in' => 'Số ngày biến động không được bằng 0.',
            'transaction_type.required' => 'Vui lòng chọn loại biến động.',
            'description.required' => 'Bắt buộc nhập lý do điều chỉnh để phục vụ kiểm toán.',
        ];
    }
}
