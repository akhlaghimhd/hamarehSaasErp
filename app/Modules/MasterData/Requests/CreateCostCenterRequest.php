<?php

namespace App\Modules\MasterData\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Context;

class CreateCostCenterRequest extends FormRequest
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
                Rule::unique('cost_centers', 'code')->where(function ($query) use ($tenantId) {
                    return $query->where('tenant_id', $tenantId)->whereNull('deleted_at');
                }),
            ],
            'name'                  => ['required', 'string', 'max:200'],
            'type'                  => ['required', 'integer', 'in:1,2,3,4,5'],
            'status'                => ['sometimes', 'integer', 'in:0,1'],
            'company_id'            => ['nullable', 'uuid'],
            'department_id'         => ['nullable', 'uuid'],
            'parent_cost_center_id' => ['nullable', 'uuid', 'exists:cost_centers,id'],
        ];
    }
}