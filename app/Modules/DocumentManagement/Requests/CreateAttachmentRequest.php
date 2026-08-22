<?php
namespace App\Modules\DocumentManagement\Requests;
use Illuminate\Foundation\Http\FormRequest;
use App\Modules\DocumentManagement\DTOs\CreateAttachmentDTO;

class CreateAttachmentRequest extends FormRequest {
    public function authorize(): bool { return true; }
    public function rules(): array {
        return [
            'target_entity_type' => ['required', 'string', 'max:100'],
            'target_entity_id' => ['required', 'uuid'],
            'file_name' => ['required', 'string', 'max:255'],
            'file_path' => ['required', 'string', 'max:500'],
            'mime_type' => ['required', 'string', 'max:100'],
            'file_size_bytes' => ['required', 'integer'],
            'file_hash' => ['nullable', 'string', 'max:128'],
        ];
    }
    public function toDTO(): CreateAttachmentDTO {
        return new CreateAttachmentDTO(
            $this->validated('target_entity_type'), $this->validated('target_entity_id'),
            $this->validated('file_name'), $this->validated('file_path'),
            $this->validated('mime_type'), $this->validated('file_size_bytes'),
            $this->validated('file_hash')
        );
    }
}