<?php

namespace App\Modules\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'              => 'sometimes|required|string|max:300',
            'description'       => 'nullable|string|max:500',
            'item_group_id'     => 'sometimes|required|uuid',
            'uom_id'            => 'sometimes|required|uuid',
            'item_type'         => 'sometimes|required|integer|in:1,2,3',
            'valuation_method'  => 'sometimes|required|integer|in:1,2',
            'extra_attributes'  => 'nullable|array',
            'status'            => 'sometimes|required|integer|in:1,2',
        ];
    }
}
