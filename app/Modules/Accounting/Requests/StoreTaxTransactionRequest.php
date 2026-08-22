<?php

namespace App\Modules\Accounting\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Modules\Accounting\DTOs\TaxTransactionDTO;

class StoreTaxTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transaction_date' => ['required', 'date'],
            'tax_type' => ['required', 'integer', 'in:1,2,3'],
            'base_amount' => ['required', 'numeric', 'min:0'],
            'tax_amount' => ['required', 'numeric', 'min:0'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'reference_document_type' => ['nullable', 'string', 'max:50'],
            'reference_document_id' => ['nullable', 'uuid'],
            'business_partner_id' => ['nullable', 'uuid'],
        ];
    }

    public function toDTO(): TaxTransactionDTO
    {
        return new TaxTransactionDTO(
            transactionDate: $this->validated('transaction_date'),
            taxType: (int) $this->validated('tax_type'),
            baseAmount: (float) $this->validated('base_amount'),
            taxAmount: (float) $this->validated('tax_amount'),
            taxRate: (float) $this->validated('tax_rate'),
            referenceDocumentType: $this->validated('reference_document_type'),
            referenceDocumentId: $this->validated('reference_document_id'),
            businessPartnerId: $this->validated('business_partner_id')
        );
    }
}