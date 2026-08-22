<?php

namespace App\Modules\IdentityCore\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Base\Context\TenantContext;

class AssignPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        return [
            'tenant_role_id' => [
                'required', 
                'uuid',
                Rule::exists('tenant_roles', 'tenant_role_id')
                    ->where('tenant_id', $tenantId)
            ],
            'permission_ids' => ['required', 'array'],
            'permission_ids.*' => [
                'uuid', 
                Rule::exists('tenant_permissions', 'tenant_permission_id')
                    ->where('tenant_id', $tenantId)
            ]
        ];
    }
}