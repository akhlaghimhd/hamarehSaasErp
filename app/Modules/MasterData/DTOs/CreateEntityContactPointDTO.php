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
        $v = $request->validated();

        return new self(
            entity_type: (string) $v['entity_type'],
            entity_id: (string) $v['entity_id'],
            contact_type: (string) $v['contact_type'],
            contact_value: (string) $v['contact_value'],
            extension: isset($v['extension']) ? (string) $v['extension'] : null,
            is_primary: (bool) ($v['is_primary'] ?? false),
            status: (int) ($v['status'] ?? 1)
        );
    }
}
