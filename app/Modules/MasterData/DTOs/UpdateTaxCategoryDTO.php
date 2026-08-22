<?php

namespace App\Modules\MasterData\DTOs;

use App\Modules\MasterData\Requests\UpdateTaxCategoryRequest;

readonly class UpdateTaxCategoryDTO
{
    public function __construct(
        public ?string $code = null,
        public ?string $name = null,
        public ?int $status = null
    ) {}

    public static function fromRequest(UpdateTaxCategoryRequest $request): self
    {
        return new self(
            code: $request->validated('code'),
            name: $request->validated('name'),
            status: $request->validated('status')
        );
    }
}