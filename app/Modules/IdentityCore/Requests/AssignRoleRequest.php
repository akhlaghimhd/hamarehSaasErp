<?php

namespace App\Modules\IdentityCore\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Base\Context\TenantContext;

class AssignRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        return [
            // کاربر حتماً باید عضو همین Tenant باشد
            'user_id' => [
                'required', 
                'uuid', 
                Rule::exists('tenant_users', 'user_id')
                    ->where('tenant_id', $tenantId)
            ], 
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => [
                'uuid', 
                Rule::exists('tenant_roles', 'tenant_role_id')
                    ->where('tenant_id', $tenantId)
            ]
        ];
    }
}