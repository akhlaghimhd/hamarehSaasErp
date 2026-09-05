<?php

namespace App\Modules\ProcurementSales\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id'   => ['required', 'uuid'],
            'currency_id'   => ['required', 'uuid'],
            'order_date'    => ['required', 'date'],
            'delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'items'         => ['required', 'array', 'min:1'],
            'items.*.item_id'         => ['required', 'uuid'],
            'items.*.quantity'        => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit_price'      => ['required', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_amount'      => ['nullable', 'numeric', 'min:0'],
            'items.*.uom_code'        => ['nullable', 'string', 'max:30'],
            'items.*.line_number'     => ['nullable', 'integer', 'min:1'],
            'items.*.description'     => ['nullable', 'string', 'max:500'],
        ];
    }
}
