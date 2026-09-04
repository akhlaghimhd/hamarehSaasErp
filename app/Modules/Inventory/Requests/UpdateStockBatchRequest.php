<?php

namespace App\Modules\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStockBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'batch_number'       => 'sometimes|required|string|max:100',
            'quantity_produced'  => 'sometimes|required|numeric|min:0.0001|regex:/^\d+(\.\d{1,4})?$/',
            'quantity_remaining' => 'sometimes|required|numeric|min:0|regex:/^\d+(\.\d{1,4})?$/',
            'production_date'    => 'nullable|date',
            'expiration_date'    => 'nullable|date',
            'qc_status'          => 'sometimes|required|integer|in:1,2,3',
        ];
    }
}
