<?php

namespace App\Modules\Organization\DTOs;

readonly class CreateDepartmentDTO
{
    public function __construct(
        public string $name,
        public string $code,
        public ?string $description,
        public int $status = 1,
    ) {}

    public static function fromRequest(array $validatedData): self
    {
        return new self(
            name: $validatedData['name'],
            code: $validatedData['code'],
            description: $validatedData['description'] ?? null,
            status: (int) ($validatedData['status'] ?? 1),
        );
    }
}