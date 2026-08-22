<?php

namespace App\Modules\MasterData\DTOs;

use App\Modules\MasterData\Requests\UpdateEntityAddressRequest;

readonly class UpdateEntityAddressDTO
{
    public function __construct(
        public ?string $entity_type = null,
        public ?string $entity_id = null,
        public ?string $address_type_id = null,
        public ?string $country_id = null,
        public ?string $address_text = null,
        public ?string $province_id = null,
        public ?string $city_id = null,
        public ?string $postal_code = null,
        public ?bool $is_primary = null,
        public ?int $status = null
    ) {}

    public static function fromRequest(UpdateEntityAddressRequest $request): self
    {
        return new self(
            entity_type: $request->validated('entity_type'),
            entity_id: $request->validated('entity_id'),
            address_type_id: $request->validated('address_type_id'),
            country_id: $request->validated('country_id'),
            address_text: $request->validated('address_text'),
            province_id: $request->validated('province_id'),
            city_id: $request->validated('city_id'),
            postal_code: $request->validated('postal_code'),
            is_primary: $request->validated('is_primary'),
            status: $request->validated('status')
        );
    }
}