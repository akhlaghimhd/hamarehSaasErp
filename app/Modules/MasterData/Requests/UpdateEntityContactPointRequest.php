<?php

namespace App\Modules\MasterData\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEntityContactPointRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'entity_type' => ['sometimes', 'string', 'max:100'],
            'entity_id' => ['sometimes', 'uuid'],
            'contact_type' => ['sometimes', 'string', 'max:50'],
            'contact_value' => ['sometimes', 'string', 'max:255'],
            'extension' => ['nullable', 'string', 'max:20'],
            'is_primary' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'integer', 'in:1,2'],
        ];
    }
}