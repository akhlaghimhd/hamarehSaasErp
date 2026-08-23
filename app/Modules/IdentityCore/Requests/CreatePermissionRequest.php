<?php

namespace App\Modules\IdentityCore\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Base\Context\TenantContext;

class CreatePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        return [
            'code' => [
                'required',
                'string',
                'max:150',
                'regex:/^[a-z0-9]+(\.[a-z0-9_-]+)+$/',
                Rule::unique('tenant_permissions', 'code')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:200'],
            'module_name' => ['required', 'string', 'max:100'],
            'action_type' => ['nullable', 'string', 'max:50', 'in:CREATE,READ,UPDATE,DELETE,APPROVE,EXECUTE'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'فرمت کد مجوز باید به صورت module.resource.action باشد (مثال: identity.role.create).',
            'code.unique' => 'این کد مجوز قبلاً در این مستأجر ثبت شده است.',
        ];
    }
}