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
            'first_name'       => ['required', 'string', 'max:100'],
            'last_name'        => ['required', 'string', 'max:100'],
            'mobile'           => ['required', 'string', 'max:20'],
            'email_local_part' => ['nullable', 'string', 'max:64', 'regex:/^[a-zA-Z0-9][a-zA-Z0-9._-]*[a-zA-Z0-9]$|^[a-zA-Z0-9]$/'],
            'is_owner'         => ['nullable', 'boolean'],
            'role_ids'         => ['nullable', 'array'],
            'role_ids.*'       => [
                'uuid',
                Rule::exists('tenant_roles', 'tenant_role_id')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.required'            => 'شماره موبایل الزامی است.',
            'first_name.required'        => 'نام الزامی است.',
            'last_name.required'         => 'نام خانوادگی الزامی است.',
            'email_local_part.regex'     => 'بخش ابتدایی ایمیل فقط شامل حروف انگلیسی، عدد، نقطه، خط تیره و زیرخط باشد.',
        ];
    }
}
