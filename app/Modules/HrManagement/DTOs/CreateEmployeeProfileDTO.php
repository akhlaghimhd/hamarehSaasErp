<?php

namespace App\Modules\HrManagement\DTOs;

readonly class CreateEmployeeProfileDTO
{
    public function __construct(
        public string $employeeId,
        public string $firstName,
        public string $lastName,
        public string $nationalCode,
        public ?string $birthDate = null,
        public ?int $gender = null,
        public ?int $maritalStatus = null,
        public ?string $address = null,
        public ?string $emergencyContact = null,
    ) {}

    public static function fromRequest(array $validated): self
    {
        return new self(
            employeeId: $validated['employee_id'],
            firstName: $validated['first_name'],
            lastName: $validated['last_name'],
            nationalCode: $validated['national_code'],
            birthDate: $validated['birth_date'] ?? null,
            gender: $validated['gender'] ?? null,
            maritalStatus: $validated['marital_status'] ?? null,
            address: $validated['address'] ?? null,
            emergencyContact: $validated['emergency_contact'] ?? null,
        );
    }
}