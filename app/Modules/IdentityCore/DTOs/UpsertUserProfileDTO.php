<?php

namespace App\Modules\IdentityCore\DTOs;

readonly class UpsertUserProfileDTO
{
    public function __construct(
        public string $userId,
        public ?string $nationalId = null,
        public ?string $birthDate = null,
        public ?string $avatarUrl = null,
        public ?int $gender = null,
        public ?string $address = null,
        public ?string $phone = null,
        public ?string $description = null,
    ) {}

    public static function fromRequest(string $userId, array $validatedData): self
    {
        return new self(
            userId: $userId,
            nationalId: array_key_exists('national_id', $validatedData)
                ? $validatedData['national_id']
                : null,
            birthDate: array_key_exists('birth_date', $validatedData)
                ? $validatedData['birth_date']
                : null,
            avatarUrl: array_key_exists('avatar_url', $validatedData)
                ? $validatedData['avatar_url']
                : null,
            gender: array_key_exists('gender', $validatedData)
                ? (is_null($validatedData['gender']) ? null : (int) $validatedData['gender'])
                : null,
            address: array_key_exists('address', $validatedData)
                ? $validatedData['address']
                : null,
            phone: array_key_exists('phone', $validatedData)
                ? $validatedData['phone']
                : null,
            description: array_key_exists('description', $validatedData)
                ? $validatedData['description']
                : null,
        );
    }
}
