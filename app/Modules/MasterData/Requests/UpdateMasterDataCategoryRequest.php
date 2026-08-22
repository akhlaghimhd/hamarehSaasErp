<?php

namespace App\Modules\MasterData\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMasterDataCategoryRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'code' => ['sometimes', 'string', 'max:100'],
            'name' => ['sometimes', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_system_category' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'integer', 'in:1,2'],
        ];
    }
}