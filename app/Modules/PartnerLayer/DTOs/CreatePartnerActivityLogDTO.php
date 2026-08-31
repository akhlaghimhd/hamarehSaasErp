<?php

namespace App\Modules\PartnerLayer\DTOs;

readonly class CreatePartnerActivityLogDTO
{
    public function __construct(
        public string $partnerId,
        public string $userId,
        public string $actionType,
        public string $description,
        public string $ipAddress,
    ) {}

    public static function fromRequest(array $d): self
    {
        return new self(
            partnerId: $d['partner_id'],
            userId: $d['user_id'],
            actionType: $d['action_type'],
            description: $d['description'],
            ipAddress: $d['ip_address'],
        );
    }
}
