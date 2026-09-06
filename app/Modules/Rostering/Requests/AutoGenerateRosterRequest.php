<?php

declare(strict_types=1);

namespace App\Modules\Rostering\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Modules\Rostering\Enums\RosteringStrategy;

class AutoGenerateRosterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'department_id' => 'nullable|exists:departments,id',
            'strategy' => ['required', Rule::enum(RosteringStrategy::class)],
            'shift_ids' => 'required|array|min:1',
            'shift_ids.*' => 'exists:shifts,id',
            'employee_ids' => 'nullable|array',
            'employee_ids.*' => 'exists:employees,id',
            'preferences' => 'nullable|array',
            'preferences.avoid_female_night' => 'nullable|boolean',
            'preferences.avoid_has_child' => 'nullable|boolean',
            'preferences.prioritize_low_base_salary' => 'nullable|boolean',
            'simulate_only' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'start_date.required' => 'Vui lòng chọn ngày bắt đầu phân ca.',
            'end_date.required' => 'Vui lòng chọn ngày kết thúc phân ca.',
            'end_date.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
            'shift_ids.required' => 'Vui lòng chọn ít nhất một ca làm việc để phân bổ.',
            'strategy.required' => 'Vui lòng chọn chiến lược phân ca.',
        ];
    }
}
