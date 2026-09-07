<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'comment' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'comment.required' => 'Vui lòng cung cấp lý do từ chối đơn để phản hồi cho nhân viên.',
            'comment.min' => 'Lý do từ chối phải có ít nhất 3 ký tự.',
            'comment.max' => 'Lý do từ chối không được vượt quá 1000 ký tự.',
        ];
    }
}
