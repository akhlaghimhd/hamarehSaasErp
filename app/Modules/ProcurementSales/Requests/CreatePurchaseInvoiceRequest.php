<?php

namespace App\Modules\ProcurementSales\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePurchaseInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id'                => ['required', 'uuid'],
            'currency_id'                => ['required', 'uuid'],
            'invoice_date'               => ['required', 'date'],
            'due_date'                   => ['nullable', 'date'],
            'purchase_order_id'          => ['nullable', 'uuid'],
            'supplier_invoice_ref'       => ['nullable', 'string', 'max:100'],
            'tax_invoice_number'         => ['nullable', 'string', 'max:100'],
            'description'                => ['nullable', 'string'],
            'items'                      => ['required', 'array', 'min:1'],
            'items.*.item_id'            => ['required', 'uuid'],
            'items.*.quantity'           => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price'         => ['required', 'numeric', 'gte:0'],
            'items.*.discount_amount'    => ['nullable', 'numeric', 'gte:0'],
            'items.*.tax_amount'         => ['nullable', 'numeric', 'gte:0'],
            'items.*.tax_definition_id'  => ['nullable', 'uuid'],
            'items.*.uom_code'           => ['nullable', 'string', 'max:30'],
            'items.*.line_number'        => ['nullable', 'integer', 'min:1'],
            'items.*.description'        => ['nullable', 'string'],
        ];
    }
}
