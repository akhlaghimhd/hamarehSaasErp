<?php

namespace App\Modules\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Context;

class UpdateItemBarcodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = Context::get('tenant_id');
        $id = $this->route('id');

        return [
            'barcode' => [
                'sometimes',
                'string',
                'max:100',
                Rule::unique('inv_item_barcodes', 'barcode')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->ignore($id, 'barcode_id'),
            ],
            'barcode_type' => 'sometimes|string|max:50',
            'sku'          => 'nullable|string|max:100',
            'is_primary'   => 'sometimes|boolean',
        ];
    }
}
