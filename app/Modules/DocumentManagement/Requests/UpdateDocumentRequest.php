<?php
namespace App\Modules\DocumentManagement\Requests;
use Illuminate\Foundation\Http\FormRequest;
use App\Modules\DocumentManagement\DTOs\UpdateDocumentDTO;

class UpdateDocumentRequest extends FormRequest {
    public function authorize(): bool { return true; }
    public function rules(): array {
        return [
            'document_number' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'document_type' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'status' => ['integer'],
        ];
    }
    public function toDTO(): UpdateDocumentDTO {
        return new UpdateDocumentDTO(
            $this->validated('document_number'), $this->validated('title'),
            $this->validated('document_type'), $this->validated('description'),
            $this->validated('status', 1)
        );
    }
}