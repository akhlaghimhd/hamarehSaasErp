<?php

namespace App\Modules\MasterData\DTOs;

use App\Modules\MasterData\Requests\CreateEntityAddressRequest;

readonly class CreateEntityAddressDTO
{
    public function __construct(
        public string $entity_type,
        public string $entity_id,
        public string $address_text,
        public ?string $address_type_id = null,
        public ?string $country_id = null,
        public ?string $province_id = null,
        public ?string $city_id = null,
        public ?string $postal_code = null,
        public bool $is_primary = false,
        public int $status = 1
    ) {
    }

    public static function fromRequest(CreateEntityAddressRequest $request): self
    {
        return new self(
            entity_type: $request->validated('entity_type'),
            entity_id: $request->validated('entity_id'),
            address_text: $request->validated('address_text'),
            address_type_id: $request->validated('address_type_id'),
            country_id: $request->validated('country_id'),
            province_id: $request->validated('province_id'),
            city_id: $request->validated('city_id'),
            postal_code: $request->validated('postal_code'),
            is_primary: (bool) $request->validated('is_primary', false),
            status: (int) $request->validated('status', 1)
        );
    }
}
