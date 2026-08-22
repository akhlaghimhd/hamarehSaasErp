<?php
namespace App\Modules\DocumentManagement\Requests;
use Illuminate\Foundation\Http\FormRequest;
use App\Modules\DocumentManagement\DTOs\CreateDocumentVersionDTO;

class CreateDocumentVersionRequest extends FormRequest {
    public function authorize(): bool { return true; }
    public function rules(): array {
        return [
            'document_id' => ['required', 'uuid'],
            'version_number' => ['required', 'integer', 'min:1'],
            'attachment_id' => ['nullable', 'uuid'],
            'change_summary' => ['nullable', 'string'],
        ];
    }
    public function toDTO(): CreateDocumentVersionDTO {
        return new CreateDocumentVersionDTO(
            $this->validated('document_id'), $this->validated('version_number'),
            $this->validated('attachment_id'), $this->validated('change_summary')
        );
    }
}