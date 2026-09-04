<?php

namespace App\Modules\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Context;

class CreateItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // مدیریت دسترسی توسط میدل‌ور کانتکست انجام می‌شود
    }

    public function rules(): array
    {
        $tenantId = Context::get('tenant_id');

        return [
            'code' => [
                'required',
                'string',
                'max:100',
                // کد کالا باید در سطح شرکت فعلی یکتا باشد
                Rule::unique('items', 'code')->where('tenant_id', $tenantId)->whereNull('deleted_at')
            ],
            'name' => 'required|string|max:255',
            'item_type' => 'required|integer|in:1,2,3', // 1: Material, 2: Product, 3: Service
            'base_uom' => 'required|string|max:50',
            'status' => 'sometimes|integer|in:1,2',
        ];
    }
}
