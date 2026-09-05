<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LockMonthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'month_year' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'lock' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'month_year.required' => 'Kỳ công tháng là bắt buộc (định dạng YYYY-MM).',
            'month_year.regex' => 'Định dạng tháng không hợp lệ (ví dụ: 2026-09).',
        ];
    }
}
