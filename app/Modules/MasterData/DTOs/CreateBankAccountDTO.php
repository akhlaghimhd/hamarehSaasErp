<?php

namespace App\Modules\MasterData\DTOs;

use App\Modules\MasterData\Requests\CreateBankAccountRequest;

readonly class CreateBankAccountDTO
{
    public function __construct(
        public string $entity_type,
        public string $entity_id,
        public string $bank_name,
        public string $account_number,
        public ?string $branch_name = null,
        public ?string $card_number = null,
        public ?string $iban = null,
        public bool $is_primary = false,
        public int $status = 1
    ) {}

    public static function fromRequest(CreateBankAccountRequest $request): self
    {
        return new self(
            entity_type: $request->validated('entity_type'),
            entity_id: $request->validated('entity_id'),
            bank_name: $request->validated('bank_name'),
            account_number: $request->validated('account_number'),
            branch_name: $request->validated('branch_name'),
            card_number: $request->validated('card_number'),
            iban: $request->validated('iban'),
            is_primary: $request->validated('is_primary', false),
            status: $request->validated('status', 1)
        );
    }
}