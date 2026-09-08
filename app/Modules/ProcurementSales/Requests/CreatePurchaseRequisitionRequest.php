<?php

namespace App\Modules\ProcurementSales\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePurchaseRequisitionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'department_id' => 'required|uuid',
            'required_date' => 'required|date',
            'priority' => 'sometimes|integer|in:1,2,3',
            'description' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|uuid',
            'items.*.quantity' => 'required|numeric|gt:0',
            'items.*.estimated_unit_price' => 'sometimes|numeric|min:0',
            'items.*.uom_code' => 'nullable|string|max:20',
            'items.*.line_number' => 'sometimes|integer|min:1',
            'items.*.description' => 'nullable|string|max:500',
        ];
    }
}
