<?php

namespace App\Modules\PartnerLayer\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePartnerDocumentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'partner_id'      => ['required', 'uuid'],
            'document_type'   => ['required', 'string', 'max:100'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'storage_path'    => ['required', 'string', 'max:1000'],
            'status'          => ['nullable', 'integer', 'in:1,2,3'],
            'verified_at'     => ['nullable', 'date'],
            'verified_by'     => ['nullable', 'uuid'],
        ];
    }
}
