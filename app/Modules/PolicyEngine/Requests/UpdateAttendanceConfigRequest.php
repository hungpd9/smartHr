<?php

declare(strict_types=1);

namespace App\Modules\PolicyEngine\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAttendanceConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'grace_period_late_minutes' => ['required', 'integer', 'min:0', 'max:60'],
            'grace_period_early_minutes' => ['required', 'integer', 'min:0', 'max:60'],
            'leave_unit_hours' => ['required', 'numeric', 'min:0.5', 'max:8'],
            'early_leave_unit_hours' => ['required', 'numeric', 'min:0.1', 'max:4'],
            'min_rest_hours_between_shifts' => ['required', 'integer', 'min:8', 'max:24'],
            'min_weekly_rest_hours' => ['required', 'integer', 'min:12', 'max:48'],
            'max_weekly_standard_hours' => ['required', 'integer', 'min:20', 'max:60'],
            'penalty_tiers_json' => ['required', 'array'],
            'penalty_tiers_json.*.min' => ['required', 'integer', 'min:1'],
            'penalty_tiers_json.*.max' => ['nullable', 'integer'],
            'penalty_tiers_json.*.fine' => ['required', 'numeric', 'min:0'],
            'frequency_penalty_json' => ['required', 'array'],
            'frequency_penalty_json.*.count' => ['required', 'integer', 'min:1'],
            'frequency_penalty_json.*.multiplier' => ['required', 'numeric', 'min:1.0'],
        ];
    }
}
