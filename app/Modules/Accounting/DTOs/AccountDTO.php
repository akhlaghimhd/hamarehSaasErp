<?php

namespace App\Modules\Accounting\DTOs;

class AccountDTO
{
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly int $accountType, // 1: Asset, 2: Liability, 3: Equity, 4: Revenue, 5: Expense
        public readonly ?string $parentAccountId = null,
        public readonly ?string $description = null,
        public readonly bool $isActive = true
    ) {}
}