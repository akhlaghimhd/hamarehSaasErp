<?php

namespace App\Modules\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'       => ['sometimes', 'required', 'string', 'max:200'],
            'branch_id'  => ['sometimes', 'required', 'uuid'],
            'is_bonded'  => ['sometimes', 'boolean'],
            'status'     => ['sometimes', 'integer', 'in:1,2'],
        ];
    }
}
