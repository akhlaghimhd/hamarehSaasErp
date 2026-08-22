<?php

namespace App\Modules\MasterData\DTOs;

use App\Modules\MasterData\Requests\UpdateMasterDataCategoryRequest;

readonly class UpdateMasterDataCategoryDTO
{
    public function __construct(
        public ?string $code = null,
        public ?string $name = null,
        public ?string $description = null,
        public ?bool $is_system_category = null,
        public ?int $status = null
    ) {}

    public static function fromRequest(UpdateMasterDataCategoryRequest $request): self
    {
        return new self(
            code: $request->validated('code'),
            name: $request->validated('name'),
            description: $request->validated('description'),
            is_system_category: $request->validated('is_system_category'),
            status: $request->validated('status')
        );
    }
}