<?php

namespace App\Modules\IdentityCore\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateScopeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scope_name'      => ['sometimes', 'string', 'max:150'],
            'scope_type'      => ['sometimes', 'string', 'max:50', 'in:COMPANY,BRANCH,WAREHOUSE,DEPARTMENT,COST_CENTER,BUSINESS_UNIT,CUSTOM'],
            'reference_id'    => ['nullable', 'uuid'],
            'reference_ids'   => ['nullable', 'array', 'min:1'],
            'reference_ids.*' => ['uuid'],
            'description'     => ['nullable', 'string', 'max:500'],
            'is_active'       => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'scope_type.in'        => 'نوع محدوده انتخاب‌شده معتبر نیست.',
            'reference_id.uuid'    => 'شناسه موجودیت مرجع نامعتبر است.',
            'reference_ids.*.uuid' => 'یکی از شناسه‌های مرجع نامعتبر است.',
        ];
    }
}
