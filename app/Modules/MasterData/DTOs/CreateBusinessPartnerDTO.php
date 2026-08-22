<?php

namespace App\Modules\MasterData\DTOs;

readonly class CreateBusinessPartnerDTO
{
    public function __construct(
        public string $code,
        public string $display_name,
        public int $partner_type,
        public int $status = 1,
        public ?string $parent_business_partner_id = null
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            code: $data['code'],
            display_name: $data['display_name'],
            partner_type: (int) $data['partner_type'],
            status: isset($data['status']) ? (int) $data['status'] : 1,
            parent_business_partner_id: $data['parent_business_partner_id'] ?? null
        );
    }
}