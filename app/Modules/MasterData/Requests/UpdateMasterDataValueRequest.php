<?php

namespace App\Modules\MasterData\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMasterDataValueRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'master_data_category_id' => ['sometimes', 'uuid'],
            'parent_master_data_value_id' => ['nullable', 'uuid'],
            'code' => ['sometimes', 'string', 'max:100'],
            'name' => ['sometimes', 'string', 'max:200'],
            'sort_order' => ['sometimes', 'integer'],
            'extra_data' => ['nullable', 'array'],
            'status' => ['sometimes', 'integer', 'in:1,2'],
        ];
    }
}