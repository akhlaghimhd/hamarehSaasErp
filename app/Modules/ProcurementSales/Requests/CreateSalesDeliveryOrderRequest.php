<?php

namespace App\Modules\ProcurementSales\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateSalesDeliveryOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sales_order_id'               => ['nullable', 'uuid'],
            'customer_id'                  => ['required', 'uuid'],
            'warehouse_id'                 => ['required', 'uuid'],
            'shipping_date'                => ['required', 'date'],
            'shipping_address'             => ['nullable', 'string'],
            'items'                        => ['required', 'array', 'min:1'],
            'items.*.item_id'              => ['required', 'uuid'],
            'items.*.delivered_quantity'   => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price'           => ['nullable', 'numeric', 'gte:0'],
            'items.*.ordered_quantity'     => ['nullable', 'numeric', 'gte:0'],
            'items.*.sales_order_item_id'  => ['nullable', 'uuid'],
            'items.*.uom_code'             => ['nullable', 'string', 'max:30'],
            'items.*.line_number'          => ['nullable', 'integer', 'min:1'],
            'items.*.notes'                => ['nullable', 'string'],
        ];
    }
}
