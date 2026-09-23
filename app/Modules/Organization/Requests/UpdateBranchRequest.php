<?php

namespace App\Modules\Organization\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id'             => ['nullable', 'uuid'],
            'code'                   => ['required', 'string', 'max:50'],
            'name'                   => ['required', 'string', 'max:200'],
            'address'                => ['nullable', 'string'],
            'is_active'              => ['boolean'],
            'branch_kind'            => ['nullable', 'string', Rule::in(['OFFICE', 'PLANT', 'WAREHOUSE_SITE', 'DISTRIBUTION', 'MIXED'])],
            'parent_branch_id'       => ['nullable', 'uuid'],
            'default_warehouse_id'   => ['nullable', 'uuid'],
            'supports_shipping'      => ['boolean'],
            'supports_receiving'     => ['boolean'],
            'is_manufacturing_site'  => ['boolean'],
        ];
    }
}
