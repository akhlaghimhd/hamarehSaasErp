<?php

namespace App\Modules\MasterData\DTOs;

use App\Modules\MasterData\Requests\CreateUnitOfMeasureRequest;

readonly class CreateUnitOfMeasureDTO
{
    public function __construct(
        public string $code,
        public string $name,
        public int $decimal_places = 0,
        public float $conversion_factor = 1.0000,
        public int $status = 1
    ) {}

    public static function fromRequest(CreateUnitOfMeasureRequest $request): self
    {
        return new self(
            code: $request->validated('code'),
            name: $request->validated('name'),
            decimal_places: $request->validated('decimal_places', 0),
            conversion_factor: $request->validated('conversion_factor', 1.0000),
            status: $request->validated('status', 1)
        );
    }
}