<?php

namespace App\Modules\MasterData\DTOs;

readonly class UpdateBusinessPartnerDTO
{
    public function __construct(
        public ?string $display_name = null,
        public ?int $partner_type = null,
        public ?int $status = null,
        public ?string $parent_business_partner_id = null
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            display_name: $data['display_name'] ?? null,
            partner_type: isset($data['partner_type']) ? (int) $data['partner_type'] : null,
            status: isset($data['status']) ? (int) $data['status'] : null,
            parent_business_partner_id: $data['parent_business_partner_id'] ?? null
        );
    }
}