<?php

declare(strict_types=1);

namespace App\Modules\PolicyEngine\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePolicyVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
            'clone_from_version_id' => ['nullable', 'integer', 'exists:policy_versions,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Mã phiên bản quy chế không được để trống.',
            'name.required' => 'Tên phiên bản quy chế không được để trống.',
            'effective_from.required' => 'Ngày bắt đầu áp dụng là bắt buộc.',
            'effective_to.after' => 'Ngày kết thúc áp dụng phải sau ngày bắt đầu.',
        ];
    }
}
