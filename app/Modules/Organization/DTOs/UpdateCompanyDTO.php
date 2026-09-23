<?php

namespace App\Modules\Organization\DTOs;

readonly class UpdateCompanyDTO
{
    public function __construct(
        public string $code,
        public string $name,
        public ?string $legalName = null,
        public ?string $tradeName = null,
        public ?int $companyType = null,
        public ?string $registrationNumber = null,
        public ?string $registrationDate = null,
        public ?string $registrationPlace = null,
        public ?string $incorporationCountryId = null,
        public ?string $economicCode = null,
        public ?string $taxIdentifier = null,
        public ?string $nationalId = null,
        public ?string $vatRegistration = null,
        public bool $isActive = true,
        public int $status = 1,
        public bool $isPrimary = false,
        public ?string $parentCompanyId = null,
        public ?string $entityKind = null,
    ) {}

    public static function fromRequest(array $validatedData): self
    {
        $isActive = (bool) ($validatedData['is_active'] ?? true);
        $status = isset($validatedData['status'])
            ? (int) $validatedData['status']
            : ($isActive ? 1 : 2);

        return new self(
            code: $validatedData['code'],
            name: $validatedData['name'],
            legalName: $validatedData['legal_name'] ?? $validatedData['name'],
            tradeName: $validatedData['trade_name'] ?? null,
            companyType: isset($validatedData['company_type']) ? (int) $validatedData['company_type'] : null,
            registrationNumber: $validatedData['registration_number'] ?? null,
            registrationDate: $validatedData['registration_date'] ?? null,
            registrationPlace: $validatedData['registration_place'] ?? null,
            incorporationCountryId: $validatedData['incorporation_country_id'] ?? null,
            economicCode: $validatedData['economic_code'] ?? null,
            taxIdentifier: $validatedData['tax_identifier'] ?? ($validatedData['economic_code'] ?? null),
            nationalId: $validatedData['national_id'] ?? null,
            vatRegistration: $validatedData['vat_registration'] ?? null,
            isActive: $isActive,
            status: $status,
            isPrimary: (bool) ($validatedData['is_primary'] ?? false),
            parentCompanyId: $validatedData['parent_company_id'] ?? null,
            entityKind: $validatedData['entity_kind'] ?? null,
        );
    }
}
