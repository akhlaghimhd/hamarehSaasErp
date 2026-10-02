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

    protected function prepareForValidation(): void
    {
        $routeUserId = $this->route('userId');
        if (is_string($routeUserId) && $routeUserId !== '' && !$this->filled('user_id')) {
            $this->merge(['user_id' => $routeUserId]);
        }
    }

    public function rules(): array
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        return [
            'user_id' => [
                'required',
                'uuid',
                Rule::exists('tenant_users', 'user_id')
                    ->where('tenant_id', $tenantId),
            ],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => [
                'uuid',
                Rule::exists('tenant_roles', 'tenant_role_id')
                    ->where('tenant_id', $tenantId),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'شناسه کاربر الزامی است.',
            'user_id.exists' => 'کاربر در این سازمان یافت نشد.',
            'role_ids.required' => 'حداقل یک نقش باید انتخاب شود.',
            'role_ids.*.exists' => 'یکی از نقش‌های انتخاب‌شده معتبر نیست.',
        ];
    }
}
