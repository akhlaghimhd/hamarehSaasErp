<?php

namespace App\Modules\Accounting\DTOs;

use App\Modules\Accounting\Requests\UpdateFiscalPeriodRequest;

readonly class UpdateFiscalPeriodDTO
{
    public function __construct(
        public ?string $name = null,
        public ?string $startDate = null,
        public ?string $endDate = null
    ) {}

    public static function fromRequest(UpdateFiscalPeriodRequest $request): self
    {
        $validated = $request->validated();

        return new self(
            name: $validated['name'] ?? null,
            startDate: $validated['start_date'] ?? null,
            endDate: $validated['end_date'] ?? null
        );
    }
}
