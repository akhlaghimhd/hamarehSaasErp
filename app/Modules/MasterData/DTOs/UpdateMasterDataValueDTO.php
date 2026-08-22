<?php

namespace App\Modules\MasterData\DTOs;

use App\Modules\MasterData\Requests\UpdateMasterDataValueRequest;

readonly class UpdateMasterDataValueDTO
{
    public function __construct(
        public ?string $master_data_category_id = null,
        public ?string $code = null,
        public ?string $name = null,
        public ?string $parent_master_data_value_id = null,
        public ?int $sort_order = null,
        public ?array $extra_data = null,
        public ?int $status = null
    ) {}

    public static function fromRequest(UpdateMasterDataValueRequest $request): self
    {
        return new self(
            master_data_category_id: $request->validated('master_data_category_id'),
            code: $request->validated('code'),
            name: $request->validated('name'),
            parent_master_data_value_id: $request->validated('parent_master_data_value_id'),
            sort_order: $request->validated('sort_order'),
            extra_data: $request->validated('extra_data'),
            status: $request->validated('status')
        );
    }
}