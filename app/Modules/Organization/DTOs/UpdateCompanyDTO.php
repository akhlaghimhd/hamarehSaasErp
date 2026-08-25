<?php

namespace App\Modules\Organization\DTOs;

readonly class UpdateCompanyDTO
{
    public function __construct(
        public ?string $name,
        public ?string $code,
        public ?string $address,
        public ?int $status,
    ) {}

    public static function fromRequest(array $validatedData): self
    {
        return new self(
            name: $validatedData['name'] ?? null,
            code: $validatedData['code'] ?? null,
            address: $validatedData['address'] ?? null,
            status: $validatedData['status'] ?? null,
        );
    }
}