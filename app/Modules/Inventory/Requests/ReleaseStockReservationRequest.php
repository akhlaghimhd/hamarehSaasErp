<?php

namespace App\Modules\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReleaseStockReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'location_id'          => ['required', 'uuid'],
            'item_id'              => ['required', 'uuid'],
            'quantity'             => ['required', 'numeric', 'gt:0'],
            'source_document_type' => ['nullable', 'string', 'max:100'],
            'source_document_id'   => ['nullable', 'uuid'],
        ];
    }
}
