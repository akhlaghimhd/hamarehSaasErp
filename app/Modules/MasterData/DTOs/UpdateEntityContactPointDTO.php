<?php

namespace App\Modules\MasterData\DTOs;

use App\Modules\MasterData\Requests\UpdateEntityContactPointRequest;

readonly class UpdateEntityContactPointDTO
{
    public function __construct(
        public ?string $entity_type = null,
        public ?string $entity_id = null,
        public ?string $contact_type = null,
        public ?string $contact_value = null,
        public ?string $extension = null,
        public ?bool $is_primary = null,
        public ?int $status = null
    ) {}

    public static function fromRequest(UpdateEntityContactPointRequest $request): self
    {
        return new self(
            entity_type: $request->validated('entity_type'),
            entity_id: $request->validated('entity_id'),
            contact_type: $request->validated('contact_type'),
            contact_value: $request->validated('contact_value'),
            extension: $request->validated('extension'),
            is_primary: $request->validated('is_primary'),
            status: $request->validated('status')
        );
    }
}