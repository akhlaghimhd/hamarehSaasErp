<?php
namespace App\Modules\DocumentManagement\Requests;
use Illuminate\Foundation\Http\FormRequest;
use App\Modules\DocumentManagement\DTOs\CreateDocumentSequenceDTO;

class CreateDocumentSequenceRequest extends FormRequest {
    public function authorize(): bool { return true; }
    public function rules(): array {
        return [
            'module_code' => ['required', 'string', 'max:50'],
            'document_type' => ['required', 'string', 'max:100'],
            'document_scope' => ['required', 'integer'],
            'company_id' => ['nullable', 'uuid'],
            'owner_type' => ['nullable', 'string', 'max:50'],
            'owner_id' => ['nullable', 'uuid'],
            'prefix' => ['nullable', 'string', 'max:20'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'padding_length' => ['integer', 'min:1', 'max:15'],
            'reset_period' => ['integer'],
        ];
    }
    public function toDTO(): CreateDocumentSequenceDTO {
        return new CreateDocumentSequenceDTO(
            $this->validated('module_code'), $this->validated('document_type'),
            $this->validated('document_scope'), $this->validated('company_id'),
            $this->validated('owner_type'), $this->validated('owner_id'),
            $this->validated('prefix'), $this->validated('suffix'),
            $this->validated('padding_length', 6), $this->validated('reset_period', 1)
        );
    }
}