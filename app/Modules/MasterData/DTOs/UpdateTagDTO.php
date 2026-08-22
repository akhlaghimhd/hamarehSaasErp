<?php

namespace App\Modules\MasterData\DTOs;

use App\Modules\MasterData\Requests\UpdateTagRequest;

readonly class UpdateTagDTO
{
    public function __construct(
        public ?string $scope_type = null,
        public ?string $tag_name = null,
        public ?string $module_code = null,
        public ?string $entity_type = null,
        public ?string $description = null
    ) {}

    public static function fromRequest(UpdateTagRequest $request): self
    {
        return new self(
            scope_type: $request->validated('scope_type'),
            tag_name: $request->validated('tag_name'),
            module_code: $request->validated('module_code'),
            entity_type: $request->validated('entity_type'),
            description: $request->validated('description')
        );
    }
}