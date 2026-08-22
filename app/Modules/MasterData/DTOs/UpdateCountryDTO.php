<?php

namespace App\Modules\MasterData\DTOs;

use App\Modules\MasterData\Requests\UpdateCountryRequest;

readonly class UpdateCountryDTO
{
    public function __construct(
        public ?string $iso_code = null,
        public ?string $name = null,
        public ?string $iso_numeric_code = null,
        public ?string $phone_code = null,
        public ?int $status = null
    ) {}

    public static function fromRequest(UpdateCountryRequest $request): self
    {
        return new self(
            iso_code: $request->validated('iso_code'),
            name: $request->validated('name'),
            iso_numeric_code: $request->validated('iso_numeric_code'),
            phone_code: $request->validated('phone_code'),
            status: $request->validated('status')
        );
    }
}