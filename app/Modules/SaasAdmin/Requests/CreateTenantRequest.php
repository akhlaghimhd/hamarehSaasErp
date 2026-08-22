<?php

namespace App\Modules\SaasAdmin\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        // در این مرحله هر کاربر لاگین شده‌ای می‌تواند درخواست ساخت شرکت بدهد
        return true;
    }

    public function rules(): array
    {
        return [
            'name'   => ['required', 'string', 'max:200'],
            'domain' => ['nullable', 'string', 'max:255', 'unique:tenants,domain'],
        ];
    }
}