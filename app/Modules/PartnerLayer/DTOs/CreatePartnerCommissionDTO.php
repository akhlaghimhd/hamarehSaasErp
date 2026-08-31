<?php

namespace App\Modules\PartnerLayer\DTOs;

readonly class CreatePartnerCommissionDTO
{
    public function __construct(
        public string $partnerId,
        public string $tenantId,
        public string $baseAmount,
        public int $commissionTypeSnapshot,
        public string $commissionValueSnapshot,
        public string $commissionAmount,
        public string $currencyId,
        public ?string $invoiceId = null,
        public ?string $commissionRuleId = null,
        public string $exchangeRate = '1.00000000',
        public int $status = 1,
        public ?string $calculatedAt = null,
        public ?string $paidAt = null,
    ) {}

    public static function fromRequest(array $validatedData): self
    {
        return new self(
            partnerId: $validatedData['partner_id'],
            tenantId: $validatedData['tenant_id'],
            baseAmount: (string) $validatedData['base_amount'],
            commissionTypeSnapshot: (int) $validatedData['commission_type_snapshot'],
            commissionValueSnapshot: (string) $validatedData['commission_value_snapshot'],
            commissionAmount: (string) $validatedData['commission_amount'],
            currencyId: $validatedData['currency_id'],
            invoiceId: $validatedData['invoice_id'] ?? null,
            commissionRuleId: $validatedData['commission_rule_id'] ?? null,
            exchangeRate: isset($validatedData['exchange_rate'])
                ? (string) $validatedData['exchange_rate']
                : '1.00000000',
            status: (int) ($validatedData['status'] ?? 1),
            calculatedAt: $validatedData['calculated_at'] ?? null,
            paidAt: $validatedData['paid_at'] ?? null,
        );
    }
}
