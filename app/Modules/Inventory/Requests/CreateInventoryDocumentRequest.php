<?php

namespace App\Modules\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Context;

class CreateInventoryDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = Context::get('tenant_id');

        return [
            'fiscal_period_id' => 'required|uuid',
            'document_type'    => 'required|integer|in:1,2,3,4',
            'document_number'  => [
                'required',
                'string',
                'max:100',
                Rule::unique('inv_documents', 'document_number')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at'),
            ],
            'posting_date'          => 'nullable|date',
            'source_document_type'  => 'nullable|string|max:100',
            'source_document_id'    => 'nullable|uuid',
            'business_partner_id'   => 'nullable|uuid',
            'description'           => 'nullable|string|max:500',
            'status'                => 'sometimes|integer|in:1,2',
        ];
    }
}
