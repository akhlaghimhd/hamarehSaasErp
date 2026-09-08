<?php

namespace App\Modules\ProcurementSales\DTOs;

readonly class RecordCashTransactionDTO
{
    public function __construct(
        public string $paymentScheduleId,
        public string $bankAccountId,
        public float $amount,
        public ?string $paymentReference = null,
        public ?string $transactionDate = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            paymentScheduleId: $data['payment_schedule_id'],
            bankAccountId: $data['bank_account_id'],
            amount: (float) $data['amount'],
            paymentReference: $data['payment_reference'] ?? null,
            transactionDate: $data['transaction_date'] ?? null,
        );
    }
}
