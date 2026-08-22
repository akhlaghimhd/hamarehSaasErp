<?php

namespace App\Modules\Manufacturing\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQualityInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'inspection_type' => ['required', 'integer', 'in:1,2,3'],
            'source_document_type' => ['nullable', 'string', 'max:100'],
            'source_document_id' => ['nullable', 'uuid'],
            'item_id' => ['required', 'uuid'],
            'batch_id' => ['nullable', 'uuid'],
            'inspection_number' => ['required', 'string', 'max:100'],
            'inspection_date' => ['required', 'date'],
            'inspector_user_id' => ['required', 'uuid'],
            'sample_quantity' => ['required', 'numeric', 'min:0'],
            'accepted_quantity' => ['required', 'numeric', 'min:0'],
            'rejected_quantity' => ['required', 'numeric', 'min:0'],
            'qc_status' => ['required', 'integer', 'in:1,2,3,4'],
            'notes' => ['nullable', 'string'],
        ];
    }
}