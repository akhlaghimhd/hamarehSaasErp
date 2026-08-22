<?php

namespace App\Modules\ProcurementSales\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // کنترل دسترسی در میدل‌ور Tenant انجام می‌شود
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'uuid'],
            'order_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'uuid'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }
}