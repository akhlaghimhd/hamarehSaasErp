<?php

namespace App\Modules\ProcurementSales\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePurchaseReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'purchase_order_id' => ['required', 'uuid'],
            'supplier_id'       => ['required', 'uuid'],
            'warehouse_id'      => ['required', 'uuid'],
            'receipt_date'      => ['required', 'date'],
            'notes'             => ['nullable', 'string', 'max:2000'],
            'items'             => ['required', 'array', 'min:1'],
            'items.*.item_id'            => ['required', 'uuid'],
            'items.*.received_quantity'  => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit_price'         => ['required', 'numeric', 'min:0'],
            'items.*.ordered_quantity'   => ['nullable', 'numeric', 'min:0'],
            'items.*.purchase_order_item_id' => ['nullable', 'uuid'],
            'items.*.uom_code'           => ['nullable', 'string', 'max:30'],
            'items.*.line_number'        => ['nullable', 'integer', 'min:1'],
            'items.*.notes'              => ['nullable', 'string', 'max:500'],
        ];
    }
}
