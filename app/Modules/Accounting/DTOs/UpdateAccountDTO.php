<?php

namespace App\Modules\Accounting\DTOs;

use App\Modules\Accounting\Requests\UpdateAccountRequest;

readonly class UpdateAccountDTO
{
    public function __construct(
        public ?string $code = null,
        public ?string $name = null,
        public ?int $accountType = null,
        public ?string $parentAccountId = null,
        public ?string $description = null,
        public ?bool $isActive = null
    ) {}

    public static function fromRequest(UpdateAccountRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            code: $validated['code'] ?? null,
            name: $validated['name'] ?? null,
            accountType: isset($validated['account_type']) ? (int) $validated['account_type'] : null,
            parentAccountId: array_key_exists('parent_account_id', $validated) ? $validated['parent_account_id'] : null,
            description: $validated['description'] ?? null,
            isActive: isset($validated['is_active']) ? (bool) $validated['is_active'] : null
        );
    }
}
