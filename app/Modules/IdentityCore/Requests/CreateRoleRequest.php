<?php

namespace App\Modules\IdentityCore\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Base\Context\TenantContext;

class CreateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    public function rules(): array
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        return [
            'role_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'permission_ids' => ['nullable', 'array'],
            // رعایت دقیق ایزوله‌سازی مستأجر در ولیدیشن
            'permission_ids.*' => [
                'uuid', 
                Rule::exists('tenant_permissions', 'tenant_permission_id')
                    ->where('tenant_id', $tenantId)
            ]
        ];
    }
}