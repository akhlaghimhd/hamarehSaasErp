<?php

namespace App\Modules\Manufacturing\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductionRoutingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'production_order_id' => ['required', 'uuid', 'exists:mfg_production_orders,production_order_id'],
            'work_center_id' => ['required', 'uuid', 'exists:mfg_work_centers,work_center_id'],
            'operation_sequence' => ['required', 'integer', 'min:1'],
            'operation_name' => ['required', 'string', 'max:200'],
            'standard_setup_time_hours' => ['nullable', 'numeric', 'min:0'],
            'standard_run_time_hours' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'integer', 'in:1,2,3'],
        ];
    }
}