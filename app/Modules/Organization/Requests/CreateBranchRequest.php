<?php

namespace App\Modules\Organization\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Modules\Organization\DTOs\CreateBranchDTO;

class CreateBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (!$this->has('company_id') && $this->route('company')) {
            $this->merge([
                'company_id' => $this->route('company'),
            ]);
        }

        if ($this->has('ref_code') && is_string($this->input('ref_code'))) {
            $this->merge([
                'ref_code' => strtoupper(trim((string) $this->input('ref_code'))),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'company_id'             => ['required', 'uuid'],
            'code'                   => ['required', 'string', 'max:50'],
            'ref_code'               => ['nullable', 'string', 'size:3', 'regex:/^[A-Z0-9]{3}$/'],
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

    public function toDTO(): CreateBranchDTO
    {
        return CreateBranchDTO::fromRequest($this->validated());
    }
}
