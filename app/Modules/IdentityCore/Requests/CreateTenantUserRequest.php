<?php

namespace App\Modules\IdentityCore\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Base\Context\TenantContext;

class CreateTenantUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        return [
            'email'      => ['required', 'email', 'max:255'],
            'password'   => ['required', 'string', 'min:8', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            'mobile'     => ['nullable', 'string', 'max:20'],
            'is_owner'   => ['nullable', 'boolean'],
            'role_ids'   => ['nullable', 'array'],
            'role_ids.*' => [
                'uuid',
                Rule::exists('tenant_roles', 'tenant_role_id')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}