<?php

namespace App\Modules\Organization\DTOs;

readonly class CreateBranchDTO
{
    public function __construct(
        public string $companyId,
        public string $code,
        public string $name,
        public ?string $address = null,
        public bool $isActive = true,
        public ?string $branchKind = null,
        public ?string $parentBranchId = null,
        public ?string $defaultWarehouseId = null,
        public bool $supportsShipping = false,
        public bool $supportsReceiving = false,
        public bool $isManufacturingSite = false,
    ) {}

    public static function fromRequest(array $validatedData): self
    {
        return new self(
            companyId: $validatedData['company_id'],
            code: $validatedData['code'],
            name: $validatedData['name'],
            address: $validatedData['address'] ?? null,
            isActive: (bool) ($validatedData['is_active'] ?? true),
            branchKind: isset($validatedData['branch_kind'])
                ? strtoupper((string) $validatedData['branch_kind'])
                : null,
            parentBranchId: $validatedData['parent_branch_id'] ?? null,
            defaultWarehouseId: $validatedData['default_warehouse_id'] ?? null,
            supportsShipping: (bool) ($validatedData['supports_shipping'] ?? false),
            supportsReceiving: (bool) ($validatedData['supports_receiving'] ?? false),
            isManufacturingSite: (bool) ($validatedData['is_manufacturing_site'] ?? false),
        );
    }
}
