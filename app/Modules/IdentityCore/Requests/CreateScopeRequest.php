<?php

namespace App\Modules\IdentityCore\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateScopeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scope_name'      => ['required', 'string', 'max:150'],
            'scope_type'      => ['required', 'string', 'max:50', 'in:COMPANY,BRANCH,WAREHOUSE,DEPARTMENT,COST_CENTER,BUSINESS_UNIT,CUSTOM'],
            'reference_id'    => ['nullable', 'uuid'],
            // empty array is valid for non-structural types; structural enforced in ScopeService
            'reference_ids'   => ['nullable', 'array'],
            'reference_ids.*' => ['uuid'],
            'description'     => ['nullable', 'string', 'max:500'],
            'is_active'       => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'scope_name.required'  => 'نام محدوده الزامی است.',
            'scope_type.required'  => 'نوع محدوده الزامی است.',
            'scope_type.in'        => 'نوع محدوده انتخاب‌شده معتبر نیست.',
            'reference_id.uuid'    => 'شناسه موجودیت مرجع نامعتبر است.',
            'reference_ids.*.uuid' => 'یکی از شناسه‌های مرجع نامعتبر است.',
        ];
    }
}
