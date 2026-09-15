<?php

namespace App\Modules\IdentityCore\DTOs;

readonly class SelfUpsertUserProfileDTO
{
    public function __construct(
        public string $userId,
        public ?string $displayBio = null,
        public bool $hasDisplayBio = false,
        public ?string $address = null,
        public bool $hasAddress = false,
    ) {}

    public static function fromRequest(string $userId, array $validated): self
    {
        $hasBio = array_key_exists('display_bio', $validated) || array_key_exists('description', $validated);
        $bio = $validated['display_bio'] ?? $validated['description'] ?? null;

        $hasAddress = array_key_exists('address', $validated);
        $address = $hasAddress ? $validated['address'] : null;

        return new self(
            userId: $userId,
            displayBio: $hasBio ? $bio : null,
            hasDisplayBio: $hasBio,
            address: $hasAddress ? $address : null,
            hasAddress: $hasAddress,
        );
    }
}
