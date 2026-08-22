<?php

namespace App\Modules\Organization\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Modules\Organization\DTOs\UpdateDepartmentDTO;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'uuid'],
            'parent_department_id' => ['nullable', 'uuid'],
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:200'],
            'manager_user_id' => ['nullable', 'uuid'],
            'is_active' => ['boolean'],
        ];
    }

    public function toDTO(): UpdateDepartmentDTO
    {
        return new UpdateDepartmentDTO(
            branchId: $this->validated('branch_id'),
            code: $this->validated('code'),
            name: $this->validated('name'),
            parentDepartmentId: $this->validated('parent_department_id'),
            managerUserId: $this->validated('manager_user_id'),
            isActive: $this->validated('is_active', true)
        );
    }
}