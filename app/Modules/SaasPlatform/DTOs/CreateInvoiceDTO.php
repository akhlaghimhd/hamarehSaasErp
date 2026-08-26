<?php

namespace App\Modules\SaasPlatform\DTOs;

readonly class CreateInvoiceDTO
{
    public function __construct(
        public string $tenantId,
        public array $items,
        public float $discountAmount = 0,
        public float $taxAmount = 0,
        public ?string $dueDate = null
    ) {
    }

    public static function fromRequest(array $validated): self
    {
        return new self(
            tenantId: $validated['tenant_id'],
            items: $validated['items'],
            discountAmount: (float) ($validated['discount_amount'] ?? 0),
            taxAmount: (float) ($validated['tax_amount'] ?? 0),
            dueDate: $validated['due_date'] ?? null
        );
    }
}