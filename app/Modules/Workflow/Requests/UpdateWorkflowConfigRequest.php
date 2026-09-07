<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Requests;

use App\Modules\Workflow\Enums\RequestType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateWorkflowConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'request_type' => ['required', new Enum(RequestType::class)],
            'total_levels' => ['required', 'integer', 'min:1', 'max:3'],
            'steps_config' => ['required', 'array', 'min:1', 'max:3'],
            'steps_config.*.level' => ['required', 'integer', 'min:1', 'max:3'],
            'steps_config.*.role_type' => ['required', 'string', 'in:DIRECT_MANAGER,DEPARTMENT_HEAD,HR_ADMIN,GENERAL_DIRECTOR'],
            'steps_config.*.title' => ['required', 'string', 'max:100'],
            'condition_rules' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'total_levels.min' => 'Quy trình phải có ít nhất 1 cấp duyệt.',
            'total_levels.max' => 'Quy trình hỗ trợ tối đa 3 cấp duyệt.',
            'steps_config.required' => 'Cấu hình các bước duyệt không được để trống.',
        ];
    }
}
