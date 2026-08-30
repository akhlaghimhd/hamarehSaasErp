<?php

namespace App\Modules\PartnerLayer\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePartnerTenantAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'partner_id'        => ['required', 'uuid'],
            'tenant_id'         => ['required', 'uuid'],
            'assignment_type'   => ['nullable', 'integer'],
            'start_date'        => ['nullable', 'date'],
            'end_date'          => ['nullable', 'date', 'after_or_equal:start_date'],
            'transfer_reason'   => ['nullable', 'string', 'max:500'],
            'assigned_by'       => ['nullable', 'uuid'],
            'status'            => ['nullable', 'integer', 'in:1,2'],
        ];
    }
}
