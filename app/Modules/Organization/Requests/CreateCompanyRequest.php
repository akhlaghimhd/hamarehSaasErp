<?php

namespace App\Modules\Organization\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // کنترل دسترسی در لایه Middleware (RequirePermission) انجام می‌شود
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:200'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'economic_code' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ];
    }

    public function toDTO(): \App\Modules\Organization\DTOs\CreateCompanyDTO
    {
        return new \App\Modules\Organization\DTOs\CreateCompanyDTO(
            code: $this->validated('code'),
            name: $this->validated('name'),
            registrationNumber: $this->validated('registration_number'),
            economicCode: $this->validated('economic_code'),
            isActive: $this->validated('is_active', true)
        );
    }
}