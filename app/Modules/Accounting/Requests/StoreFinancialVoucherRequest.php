<?php

namespace App\Modules\Accounting\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Modules\Accounting\DTOs\FinancialVoucherDTO;

class StoreFinancialVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // کنترل دسترسی در لایه Gate/Policy یا میدل‌ور انجام می‌شود
    }

    public function rules(): array
    {
        return [
            'voucher_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:1000'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'reference_number' => ['required', 'string', 'max:100'],
            'currency_id' => ['nullable', 'uuid']
        ];
    }

    public function toDTO(): FinancialVoucherDTO
    {
        return new FinancialVoucherDTO(
            voucherDate: $this->validated('voucher_date'),
            description: $this->validated('description'),
            totalAmount: (float) $this->validated('total_amount'),
            referenceNumber: $this->validated('reference_number'),
            currencyId: $this->validated('currency_id')
        );
    }
}