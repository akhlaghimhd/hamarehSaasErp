<?php

namespace App\Modules\MasterData\DTOs;

use App\Modules\MasterData\Requests\CreateMasterDataCategoryRequest;

readonly class CreateMasterDataCategoryDTO
{
    public function __construct(
        public string $code,
        public string $name,
        public ?string $description = null,
        public bool $is_system_category = false,
        public int $status = 1
    ) {}

    public static function fromRequest(CreateMasterDataCategoryRequest $request): self
    {
        return new self(
            code: $request->validated('code'),
            name: $request->validated('name'),
            description: $request->validated('description'),
            is_system_category: $request->validated('is_system_category', false),
            status: $request->validated('status', 1)
        );
    }
}