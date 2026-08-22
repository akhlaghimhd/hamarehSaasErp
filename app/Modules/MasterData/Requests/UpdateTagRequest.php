<?php

namespace App\Modules\MasterData\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTagRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'scope_type' => ['sometimes', 'string', 'max:50'],
            'module_code' => ['nullable', 'string', 'max:50'],
            'entity_type' => ['nullable', 'string', 'max:100'],
            'tag_name' => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}