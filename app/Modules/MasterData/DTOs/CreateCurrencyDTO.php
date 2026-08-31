<?php

namespace App\Modules\MasterData\DTOs;

use App\Modules\MasterData\Requests\CreateCurrencyRequest;

readonly class CreateCurrencyDTO
{
    public function __construct(
        public string $code,
        public string $name,
        public ?string $symbol = null,
        public bool $isDefault = false,
        public bool $status = true,
    ) {}

    public static function fromRequest(CreateCurrencyRequest $request): self
    {
        return new self(
            code: $request->validated('code'),
            name: $request->validated('name'),
            symbol: $request->validated('symbol'),
            isDefault: (bool) $request->validated('is_default', false),
            status: (bool) $request->validated('status', true),
        );
    }
}
