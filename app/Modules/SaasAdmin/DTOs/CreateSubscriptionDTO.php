<?php

namespace App\Modules\SaasAdmin\DTOs;

readonly class CreateSubscriptionDTO
{
    public function __construct(
        public string $tenantId,
        public string $planVersionId,
        public ?string $startDate = null
    ) {
    }

    public static function fromRequest(array $validated): self
    {
        return new self(
            tenantId: $validated['tenant_id'],
            planVersionId: $validated['plan_version_id'],
            startDate: $validated['start_date'] ?? null
        );
    }
}