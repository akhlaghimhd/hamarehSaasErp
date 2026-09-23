<?php

namespace App\Modules\Organization\DTOs;

readonly class UpdateBranchDTO
{
    public function __construct(
        public string $code,
        public string $name,
        public ?string $companyId = null,
        public ?string $address = null,
        public bool $isActive = true,
        public ?string $branchKind = null,
        public bool $branchKindProvided = false,
        public ?string $parentBranchId = null,
        public bool $parentBranchIdProvided = false,
        public ?string $defaultWarehouseId = null,
        public bool $defaultWarehouseIdProvided = false,
        public ?bool $supportsShipping = null,
        public ?bool $supportsReceiving = null,
        public ?bool $isManufacturingSite = null,
    ) {}

    public static function fromRequest(array $validatedData): self
    {
        return new self(
            code: $validatedData['code'],
            name: $validatedData['name'],
            companyId: $validatedData['company_id'] ?? null,
            address: $validatedData['address'] ?? null,
            isActive: (bool) ($validatedData['is_active'] ?? true),
            branchKind: isset($validatedData['branch_kind'])
                ? strtoupper((string) $validatedData['branch_kind'])
                : null,
            branchKindProvided: array_key_exists('branch_kind', $validatedData),
            parentBranchId: $validatedData['parent_branch_id'] ?? null,
            parentBranchIdProvided: array_key_exists('parent_branch_id', $validatedData),
            defaultWarehouseId: $validatedData['default_warehouse_id'] ?? null,
            defaultWarehouseIdProvided: array_key_exists('default_warehouse_id', $validatedData),
            supportsShipping: array_key_exists('supports_shipping', $validatedData)
                ? (bool) $validatedData['supports_shipping']
                : null,
            supportsReceiving: array_key_exists('supports_receiving', $validatedData)
                ? (bool) $validatedData['supports_receiving']
                : null,
            isManufacturingSite: array_key_exists('is_manufacturing_site', $validatedData)
                ? (bool) $validatedData['is_manufacturing_site']
                : null,
        );
    }
}
