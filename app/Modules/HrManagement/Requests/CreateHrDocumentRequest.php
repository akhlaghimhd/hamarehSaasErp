<?php

namespace App\Modules\HrManagement\Requests;

use App\Modules\HrManagement\DTOs\CreateHrDocumentDTO;
use Illuminate\Foundation\Http\FormRequest;

class CreateHrDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'uuid', 'exists:employees,id'],
            'document_type_code' => ['required', 'string', 'max:100'],
            'document_title' => ['required', 'string', 'max:200'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'attachment_id' => ['nullable', 'uuid'],
            'status' => ['required', 'integer', 'in:1,2,3'], // 1: Active, 2: Expired, 3: Revoked (به عنوان مثال)
        ];
    }

    public function toDTO(): CreateHrDocumentDTO
    {
        return new CreateHrDocumentDTO(
            employee_id: $this->validated('employee_id'),
            document_type_code: $this->validated('document_type_code'),
            document_title: $this->validated('document_title'),
            issue_date: $this->validated('issue_date'),
            expiry_date: $this->validated('expiry_date'),
            attachment_id: $this->validated('attachment_id'),
            status: $this->validated('status')
        );
    }
}