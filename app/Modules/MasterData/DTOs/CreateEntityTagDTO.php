<?php

namespace App\Modules\MasterData\DTOs;

use App\Modules\MasterData\Requests\CreateEntityTagRequest;

readonly class CreateEntityTagDTO
{
    public function __construct(
        public string $tag_id,
        public string $target_entity_type,
        public string $target_entity_id,
        public ?string $assigned_by = null
    ) {}

    public static function fromRequest(CreateEntityTagRequest $request): self
    {
        return new self(
            tag_id: $request->validated('tag_id'),
            target_entity_type: $request->validated('target_entity_type'),
            target_entity_id: $request->validated('target_entity_id'),
            assigned_by: $request->validated('assigned_by')
        );
    }
}