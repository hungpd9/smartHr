<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'comment' => ['nullable', 'string', 'max:1000'],
            'verified_child_under_12m_consent' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'comment.max' => 'Ý kiến phê duyệt không được vượt quá 1000 ký tự.',
        ];
    }
}
