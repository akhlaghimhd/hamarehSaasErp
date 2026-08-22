<?php

namespace App\Modules\MasterData\DTOs;

use App\Modules\MasterData\Requests\UpdateEntityTagRequest;

readonly class UpdateEntityTagDTO
{
    public function __construct(
        public ?string $tag_id = null,
        public ?string $target_entity_type = null,
        public ?string $target_entity_id = null
    ) {}

    public static function fromRequest(UpdateEntityTagRequest $request): self
    {
        return new self(
            tag_id: $request->validated('tag_id'),
            target_entity_type: $request->validated('target_entity_type'),
            target_entity_id: $request->validated('target_entity_id')
        );
    }
}