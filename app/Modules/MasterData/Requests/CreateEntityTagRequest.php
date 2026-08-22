<?php

namespace App\Modules\MasterData\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateEntityTagRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'tag_id' => ['required', 'uuid'],
            'target_entity_type' => ['required', 'string', 'max:100'],
            'target_entity_id' => ['required', 'uuid'],
            'assigned_by' => ['nullable', 'uuid'],
        ];
    }
}