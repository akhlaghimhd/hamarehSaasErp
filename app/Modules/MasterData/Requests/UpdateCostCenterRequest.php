<?php

namespace App\Modules\MasterData\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCostCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                  => ['sometimes', 'required', 'string', 'max:200'],
            'type'                  => ['sometimes', 'required', 'integer', 'in:1,2,3,4,5'],
            'status'                => ['sometimes', 'required', 'integer', 'in:0,1'],
            'company_id'            => ['nullable', 'uuid'],
            'department_id'         => ['nullable', 'uuid'],
            'parent_cost_center_id' => ['nullable', 'uuid', 'exists:cost_centers,id'],
        ];
    }
}