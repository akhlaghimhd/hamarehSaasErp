<?php

namespace App\Modules\Manufacturing\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductionLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'production_order_id' => ['required', 'uuid', 'exists:mfg_production_orders,production_order_id'],
            'routing_id' => ['nullable', 'uuid'], // در صورت وجود مسیردهی
            'log_type' => ['required', 'integer', 'in:1,2,3'],
            'item_id' => ['nullable', 'uuid', 'required_if:log_type,1,3'], // کالا فقط در مصرف و ضایعات الزامی است
            'quantity_consumed' => ['nullable', 'numeric', 'min:0', 'required_if:log_type,1,3'],
            'hours_spent' => ['nullable', 'numeric', 'min:0', 'required_if:log_type,2'],
            'logged_at' => ['required', 'date'],
        ];
    }
}