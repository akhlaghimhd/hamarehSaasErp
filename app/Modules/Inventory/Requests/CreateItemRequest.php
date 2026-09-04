<?php

namespace App\Modules\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Context;

class CreateItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = Context::get('tenant_id');

        return [
            'code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('inv_items', 'code')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
            'name'              => 'required|string|max:300',
            'item_group_id'     => 'required|uuid',
            'uom_id'            => 'required|uuid',
            'description'       => 'nullable|string|max:500',
            'item_type'         => 'sometimes|integer|in:1,2,3',
            'valuation_method'  => 'sometimes|integer|in:1,2',
            'extra_attributes'  => 'nullable|array',
            'status'            => 'sometimes|integer|in:1,2',
        ];
    }
}
