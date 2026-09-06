<?php

declare(strict_types=1);

namespace App\Modules\Rostering\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $shiftId = $this->route('shift') ?? $this->input('id');

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                $shiftId ? "unique:shifts,code,{$shiftId},id,tenant_id,{$this->user()->tenant_id}" : "unique:shifts,code,NULL,id,tenant_id,{$this->user()->tenant_id}",
            ],
            'name' => 'required|string|max:100',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'break_minutes' => 'nullable|integer|min:0|max:300',
            'is_night_shift' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
            'night_allowance_rate' => 'nullable|numeric|min:0|max:100',
            'night_bonus_amount' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Mã ca làm việc không được để trống.',
            'code.unique' => 'Mã ca làm việc này đã tồn tại trong doanh nghiệp.',
            'name.required' => 'Tên ca làm việc không được để trống.',
            'start_time.required' => 'Giờ bắt đầu ca không được để trống.',
            'end_time.required' => 'Giờ kết thúc ca không được để trống.',
        ];
    }
}
