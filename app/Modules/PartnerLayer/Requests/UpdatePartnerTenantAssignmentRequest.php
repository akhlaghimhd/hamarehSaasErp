<?php

namespace App\Modules\PartnerLayer\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePartnerTenantAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'assignment_type' => ['nullable', 'integer'],
            'end_date'        => ['nullable', 'date'],
            'transfer_reason' => ['nullable', 'string', 'max:500'],
            'status'          => ['nullable', 'integer', 'in:1,2'],
        ];
    }
}
