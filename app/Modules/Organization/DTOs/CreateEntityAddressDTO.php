<?php

namespace App\Modules\Organization\DTOs;

readonly class CreateEntityAddressDTO
{
    public function __construct(
        public string $entityType,
        public string $entityId,
        public string $addressText,
        public ?string $addressTypeId = null,
        public ?string $countryId = null,
        public ?string $provinceName = null,
        public ?string $cityName = null,
        public ?string $postalCode = null,
        public bool $isPrimary = false,
        public int $status = 1,
    ) {}

    public static function fromRequest(array $validatedData): self
    {
        return new self(
            entityType: $validatedData['entity_type'],
            entityId: $validatedData['entity_id'],
            addressText: $validatedData['address_text'],
            addressTypeId: $validatedData['address_type_id'] ?? null,
            countryId: $validatedData['country_id'] ?? null,
            provinceName: $validatedData['province_name'] ?? null,
            cityName: $validatedData['city_name'] ?? null,
            postalCode: $validatedData['postal_code'] ?? null,
            isPrimary: (bool) ($validatedData['is_primary'] ?? false),
            status: (int) ($validatedData['status'] ?? 1),
        );
    }
}
