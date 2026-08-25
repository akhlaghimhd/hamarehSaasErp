<?php

namespace App\Modules\SaasAdmin\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id'        => ['required', 'uuid'],
            'plan_version_id'  => ['required', 'uuid'],
            'start_date'       => ['nullable', 'date'],
        ];
    }
}