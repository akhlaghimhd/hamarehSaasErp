<?php

namespace App\Modules\Organization\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Modules\Organization\DTOs\UpdateBranchDTO;

class UpdateBranchRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'uuid'],
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:200'],
            'address' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }

    public function toDTO(): UpdateBranchDTO
    {
        return new UpdateBranchDTO(
            companyId: $this->validated('company_id'),
            code: $this->validated('code'),
            name: $this->validated('name'),
            address: $this->validated('address'),
            isActive: $this->validated('is_active', true)
        );
    }
}