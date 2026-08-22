<?php

namespace App\Modules\MasterData\DTOs;

use App\Modules\MasterData\Requests\CreateMasterDataValueRequest;

readonly class CreateMasterDataValueDTO
{
    public function __construct(
        public string $master_data_category_id,
        public string $code,
        public string $name,
        public ?string $parent_master_data_value_id = null,
        public int $sort_order = 0,
        public ?array $extra_data = null,
        public int $status = 1
    ) {}

    public static function fromRequest(CreateMasterDataValueRequest $request): self
    {
        return new self(
            master_data_category_id: $request->validated('master_data_category_id'),
            code: $request->validated('code'),
            name: $request->validated('name'),
            parent_master_data_value_id: $request->validated('parent_master_data_value_id'),
            sort_order: $request->validated('sort_order', 0),
            extra_data: $request->validated('extra_data'),
            status: $request->validated('status', 1)
        );
    }
}