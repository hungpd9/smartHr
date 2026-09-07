<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Requests;

use App\Modules\Workflow\Enums\RequestType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class CreateApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'request_type' => ['required', new Enum(RequestType::class)],
            'form_payload' => ['required', 'array'],
            'is_voluntary_consent' => ['sometimes', 'boolean'],
            'voluntary_agreement_file' => ['nullable', 'string', 'max:255'],
            'voluntary_agreement_url' => ['nullable', 'url', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'request_type.required' => 'Vui lòng chọn loại đơn cần nộp.',
            'form_payload.required' => 'Nội dung chi tiết của đơn không được để trống.',
            'form_payload.array' => 'Dữ liệu biểu mẫu không đúng định dạng.',
            'voluntary_agreement_url.url' => 'Đường dẫn liên kết văn bản thỏa thuận không đúng định dạng URL.',
        ];
    }
}
