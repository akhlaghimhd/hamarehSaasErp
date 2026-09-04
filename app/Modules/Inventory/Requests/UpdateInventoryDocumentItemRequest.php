<?php

namespace App\Modules\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Context;

class UpdateInventoryDocumentItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = Context::get('tenant_id');

        return [
            'quantity'  => 'sometimes|required|numeric|gt:0',
            'unit_cost' => 'nullable|numeric|min:0',
            'from_location_id' => [
                'nullable',
                'uuid',
                Rule::exists('inv_locations', 'location_id')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at'),
            ],
            'to_location_id' => [
                'nullable',
                'uuid',
                Rule::exists('inv_locations', 'location_id')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at'),
            ],
            'batch_number' => 'nullable|string|max:100',
            'sort_order'   => 'nullable|integer|min:0',
        ];
    }
}
