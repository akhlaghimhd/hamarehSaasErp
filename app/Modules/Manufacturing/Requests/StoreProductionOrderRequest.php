<?php

namespace App\Modules\Manufacturing\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductionOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_number' => ['required', 'string', 'max:100'],
            'item_id' => ['required', 'uuid'], // ارجاع منطقی به کالا
            'bom_id' => ['required', 'uuid', 'exists:mfg_boms,bom_id'], // بررسی وجود BOM
            'planned_quantity' => ['required', 'numeric', 'min:0.0001'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', 'integer', 'in:1,2,3,4,5'],
        ];
    }
}