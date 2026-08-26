<?php

namespace App\Modules\SaasPlatform\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is enforced by permission middleware (saas-admin.tenant.create)
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_code' => [
                'required',
                'string',
                'max:100',
                'regex:/^[A-Z0-9_\-]+$/',
                Rule::unique('tenants', 'tenant_code')->whereNull('deleted_at'),
            ],
            'tenant_name' => ['required', 'string', 'max:200'],
            'legal_name'  => ['nullable', 'string', 'max:300'],
            'slug'        => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9\-]+$/',
                Rule::unique('tenants', 'slug')->whereNull('deleted_at'),
            ],
            'tenant_type' => ['nullable', 'integer', 'in:1,2,3'],
        ];
    }

    public function messages(): array
    {
        return [
            'tenant_code.regex' => 'Tenant code must contain only uppercase letters, numbers, underscores and hyphens.',
            'slug.regex'        => 'Slug must contain only lowercase letters, numbers and hyphens.',
        ];
    }
}