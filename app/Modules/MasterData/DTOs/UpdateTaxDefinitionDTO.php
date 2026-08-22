<?php

namespace App\Modules\MasterData\DTOs;

use App\Modules\MasterData\Requests\UpdateTaxDefinitionRequest;

readonly class UpdateTaxDefinitionDTO
{
    public function __construct(
        public ?string $tax_category_id = null,
        public ?string $code = null,
        public ?string $name = null,
        public ?int $tax_type = null,
        public ?float $tax_rate = null,
        public ?int $calculation_type = null,
        public ?int $status = null
    ) {}

    public static function fromRequest(UpdateTaxDefinitionRequest $request): self
    {
        return new self(
            tax_category_id: $request->validated('tax_category_id'),
            code: $request->validated('code'),
            name: $request->validated('name'),
            tax_type: $request->validated('tax_type'),
            tax_rate: $request->validated('tax_rate'),
            calculation_type: $request->validated('calculation_type'),
            status: $request->validated('status')
        );
    }
}