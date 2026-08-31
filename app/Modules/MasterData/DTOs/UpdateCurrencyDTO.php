<?php

namespace App\Modules\MasterData\DTOs;

use App\Modules\MasterData\Requests\UpdateCurrencyRequest;

readonly class UpdateCurrencyDTO
{
    public function __construct(
        public ?string $code = null,
        public ?string $name = null,
        public ?string $symbol = null,
        public ?bool $isDefault = null,
        public ?bool $status = null,
    ) {}

    public static function fromRequest(UpdateCurrencyRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            code: $validated['code'] ?? null,
            name: $validated['name'] ?? null,
            symbol: array_key_exists('symbol', $validated) ? $validated['symbol'] : null,
            isDefault: array_key_exists('is_default', $validated) ? (bool) $validated['is_default'] : null,
            status: array_key_exists('status', $validated) ? (bool) $validated['status'] : null,
        );
    }
}
