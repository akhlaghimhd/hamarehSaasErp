<?php

namespace App\Modules\ProcurementSales\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordCashTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_schedule_id' => 'required|uuid',
            'bank_account_id'     => 'required|uuid',
            'amount'              => 'required|numeric|gt:0',
            'payment_reference'   => 'nullable|string|max:150',
            'transaction_date'    => 'nullable|date',
        ];
    }
}
