<?php

namespace App\Modules\MasterData\DTOs;

use App\Modules\MasterData\Requests\CreateEntityContactPointRequest;

readonly class CreateEntityContactPointDTO
{
    public function __construct(
        public string $entity_type,
        public string $entity_id,
        public string $contact_type,
        public string $contact_value,
        public ?string $extension = null,
        public bool $is_primary = false,
        public int $status = 1
    ) {}

    public static function fromRequest(CreateEntityContactPointRequest $request): self
    {
        return new self(
            entity_type: $request->validated('entity_type'),
            entity_id: $request->validated('entity_id'),
            contact_type: $request->validated('contact_type'),
            contact_value: $request->validated('contact_value'),
            extension: $request->validated('extension'),
            is_primary: $request->validated('is_primary', false),
            status: $request->validated('status', 1)
        );
    }
}