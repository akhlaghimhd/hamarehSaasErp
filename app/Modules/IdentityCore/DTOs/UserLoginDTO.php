<?php

namespace App\Modules\IdentityCore\DTOs;

readonly class UserLoginDTO
{
    public function __construct(
        public string $email,
        public string $password
    ) {
    }

    public static function fromRequest(array $validatedData): self
    {
        return new self(
            email: $validatedData['email'],
            password: $validatedData['password']
        );
    }
}