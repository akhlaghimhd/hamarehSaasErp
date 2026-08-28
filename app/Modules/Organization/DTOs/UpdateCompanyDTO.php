<?php

namespace App\Modules\Organization\DTOs;

readonly class UpdateCompanyDTO
{
    public function __construct(
        public string $code,
        public string $name,
        public ?string $registrationNumber = null,
        public ?string $economicCode = null,
        public bool $isActive = true,
    ) {}

    public static function fromRequest(array $validatedData): self
    {
        return new self(
            code: $validatedData['code'],
            name: $validatedData['name'],
            registrationNumber: $validatedData['registration_number'] ?? null,
            economicCode: $validatedData['economic_code'] ?? null,
            isActive: (bool) ($validatedData['is_active'] ?? true),
        );
    }
}