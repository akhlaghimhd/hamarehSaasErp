<?php

namespace App\Modules\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Context;

class CreateWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
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
                Rule::unique('inv_warehouses', 'code')->where(function ($query) use ($tenantId) {
                    return $query->where('tenant_id', $tenantId)->whereNull('deleted_at');
                }),
            ],
            'name'       => ['required', 'string', 'max:200'],
            'branch_id'  => ['required', 'uuid'],
            'is_bonded'  => ['sometimes', 'boolean'],
            'status'     => ['sometimes', 'integer', 'in:1,2'],
        ];
    }
}
