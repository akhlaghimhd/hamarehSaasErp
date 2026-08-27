<?php

namespace App\Modules\SaasPlatform\DTOs;

readonly class CreateAddonDTO
{
    public function __construct(
        public string $code,
        public string $name
    ) {
    }

    public static function fromRequest(array $validated): self
    {
        return new self(
            code: $validated['code'],
            name: $validated['name']
        );
    }
}