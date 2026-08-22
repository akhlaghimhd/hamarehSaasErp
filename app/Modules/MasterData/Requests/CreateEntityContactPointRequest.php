<?php

namespace App\Modules\MasterData\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateEntityContactPointRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'entity_type' => ['required', 'string', 'max:100'],
            'entity_id' => ['required', 'uuid'],
            'contact_type' => ['required', 'string', 'max:50'],
            'contact_value' => ['required', 'string', 'max:255'],
            'extension' => ['nullable', 'string', 'max:20'],
            'is_primary' => ['boolean'],
            'status' => ['integer', 'in:1,2'],
        ];
    }
}