<?php

namespace App\Modules\MasterData\DTOs;

use App\Modules\MasterData\Requests\CreateTaxDefinitionRequest;

readonly class CreateTaxDefinitionDTO
{
    public function __construct(
        public string $tax_category_id,
        public string $code,
        public string $name,
        public int $tax_type,
        public float $tax_rate,
        public int $calculation_type = 1,
        public int $status = 1
    ) {}

    public static function fromRequest(CreateTaxDefinitionRequest $request): self
    {
        return new self(
            tax_category_id: $request->validated('tax_category_id'),
            code: $request->validated('code'),
            name: $request->validated('name'),
            tax_type: $request->validated('tax_type'),
            tax_rate: $request->validated('tax_rate', 0.0000),
            calculation_type: $request->validated('calculation_type', 1),
            status: $request->validated('status', 1)
        );
    }
}