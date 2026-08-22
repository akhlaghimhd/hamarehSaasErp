<?php

namespace App\Modules\MasterData\DTOs;

use App\Modules\MasterData\Requests\CreateCountryRequest;

readonly class CreateCountryDTO
{
    public function __construct(
        public string $iso_code,
        public string $name,
        public ?string $iso_numeric_code = null,
        public ?string $phone_code = null,
        public int $status = 1
    ) {}

    public static function fromRequest(CreateCountryRequest $request): self
    {
        return new self(
            iso_code: $request->validated('iso_code'),
            name: $request->validated('name'),
            iso_numeric_code: $request->validated('iso_numeric_code'),
            phone_code: $request->validated('phone_code'),
            status: $request->validated('status', 1)
        );
    }
}