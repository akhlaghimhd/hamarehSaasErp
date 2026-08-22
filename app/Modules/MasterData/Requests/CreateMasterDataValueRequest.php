<?php

namespace App\Modules\MasterData\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateMasterDataValueRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'master_data_category_id' => ['required', 'uuid'],
            'parent_master_data_value_id' => ['nullable', 'uuid'],
            'code' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:200'],
            'sort_order' => ['integer'],
            'extra_data' => ['nullable', 'array'],
            'status' => ['integer', 'in:1,2'],
        ];
    }
}