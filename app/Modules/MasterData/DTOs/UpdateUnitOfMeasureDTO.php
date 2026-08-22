<?php

namespace App\Modules\MasterData\DTOs;

use App\Modules\MasterData\Requests\UpdateUnitOfMeasureRequest;

readonly class UpdateUnitOfMeasureDTO
{
    public function __construct(
        public ?string $code = null,
        public ?string $name = null,
        public ?int $decimal_places = null,
        public ?float $conversion_factor = null,
        public ?int $status = null
    ) {}

    public static function fromRequest(UpdateUnitOfMeasureRequest $request): self
    {
        return new self(
            code: $request->validated('code'),
            name: $request->validated('name'),
            decimal_places: $request->validated('decimal_places'),
            conversion_factor: $request->validated('conversion_factor'),
            status: $request->validated('status')
        );
    }
}