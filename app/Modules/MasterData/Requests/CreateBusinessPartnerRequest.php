<?php

namespace App\Modules\MasterData\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Context;

class CreateBusinessPartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        // در اینجا فرض بر این است که میدل‌ور tenant.context دسترسی کاربر را تأیید کرده است
        return true;
    }

    public function rules(): array
    {
        $tenantId = Context::get('tenant_id');

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                // کد پارتنر باید در سطح یک مستأجر یکتا باشد (نه در کل سیستم)
                Rule::unique('business_partners', 'code')->where('tenant_id', $tenantId)->whereNull('deleted_at')
            ],
            'display_name' => 'required|string|max:200',
            'partner_type' => 'required|integer|in:1,2', // 1: Individual, 2: Organization
            'status' => 'sometimes|integer|in:1,2,3',
            'parent_business_partner_id' => 'nullable|uuid|exists:business_partners,business_partner_id',
        ];
    }
}