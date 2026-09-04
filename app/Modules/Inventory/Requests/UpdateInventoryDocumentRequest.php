<?php

namespace App\Modules\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInventoryDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'posting_date'         => 'nullable|date',
            'source_document_type' => 'nullable|string|max:100',
            'source_document_id'   => 'nullable|uuid',
            'business_partner_id'  => 'nullable|uuid',
            'description'          => 'nullable|string|max:500',
            'status'               => 'sometimes|integer|in:1,2',
        ];
    }
}
