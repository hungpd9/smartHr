<?php

declare(strict_types=1);

namespace App\Modules\Rostering\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateSwapRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'requester_roster_id' => 'required|exists:rosters,id',
            'target_employee_id' => 'required|exists:employees,id|different:requester_id',
            'target_roster_id' => 'nullable|exists:rosters,id',
            'swap_type' => 'required|in:SWAP,COVER',
            'reason' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'requester_roster_id.required' => 'Vui lòng chọn ca làm việc của bạn cần đổi.',
            'target_employee_id.required' => 'Vui lòng chọn đồng nghiệp muốn đổi ca cùng.',
            'target_employee_id.different' => 'Không thể đổi ca với chính bản thân.',
        ];
    }
}
