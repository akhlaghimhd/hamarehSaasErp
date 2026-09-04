<?php

namespace App\Modules\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Context;

class CreateStockBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = Context::get('tenant_id');
        $itemId = $this->input('item_id');

        return [
            'item_id' => [
                'required',
                'uuid',
                Rule::exists('inv_items', 'item_id')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at'),
            ],
            'batch_number' => [
                'required',
                'string',
                'max:100',
                Rule::unique('inv_stock_batches', 'batch_number')
                    ->where('tenant_id', $tenantId)
                    ->where('item_id', $itemId)
                    ->whereNull('deleted_at'),
            ],
            'quantity_produced'  => 'required|numeric|min:0.0001|regex:/^\d+(\.\d{1,4})?$/',
            'quantity_remaining' => 'sometimes|numeric|min:0|regex:/^\d+(\.\d{1,4})?$/',
            'production_date'    => 'nullable|date',
            'expiration_date'    => 'nullable|date|after_or_equal:production_date',
            'qc_status'          => 'sometimes|integer|in:1,2,3',
        ];
    }
}
