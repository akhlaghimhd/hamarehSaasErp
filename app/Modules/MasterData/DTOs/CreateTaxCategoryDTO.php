<?php

namespace App\Modules\MasterData\DTOs;

use App\Modules\MasterData\Requests\CreateTaxCategoryRequest;

readonly class CreateTaxCategoryDTO
{
    public function __construct(
        public string $code,
        public string $name,
        public int $status = 1
    ) {}

    public static function fromRequest(CreateTaxCategoryRequest $request): self
    {
        return new self(
            code: $request->validated('code'),
            name: $request->validated('name'),
            status: $request->validated('status', 1)
        );
    }
}