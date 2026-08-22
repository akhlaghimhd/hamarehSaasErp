<?php

namespace App\Modules\MasterData\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEntityTagRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'tag_id' => ['sometimes', 'uuid'],
            'target_entity_type' => ['sometimes', 'string', 'max:100'],
            'target_entity_id' => ['sometimes', 'uuid'],
        ];
    }
}