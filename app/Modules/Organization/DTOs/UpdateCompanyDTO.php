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
        public ?bool $isPrimary = null,
        public ?string $parentCompanyId = null,
        public ?string $entityKind = null,
        public bool $parentCompanyIdProvided = false,
        public ?string $baseCurrencyId = null,
        public bool $baseCurrencyIdProvided = false,
        public ?string $chartOfAccountsId = null,
        public bool $chartOfAccountsIdProvided = false,
        public ?string $defaultConsolRateType = null,
        public bool $defaultConsolRateTypeProvided = false,
    ) {}

    public static function fromRequest(array $validatedData): self
    {
        $isActive = (bool) ($validatedData['is_active'] ?? true);
        $status = isset($validatedData['status'])
            ? (int) $validatedData['status']
            : ($isActive ? 1 : 2);

        $isPrimary = array_key_exists('is_primary', $validatedData)
            ? (bool) $validatedData['is_primary']
            : null;

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
            isPrimary: $isPrimary,
            parentCompanyId: $validatedData['parent_company_id'] ?? null,
            entityKind: $validatedData['entity_kind'] ?? null,
            parentCompanyIdProvided: array_key_exists('parent_company_id', $validatedData),
            baseCurrencyId: $validatedData['base_currency_id'] ?? null,
            baseCurrencyIdProvided: array_key_exists('base_currency_id', $validatedData),
            chartOfAccountsId: $validatedData['chart_of_accounts_id'] ?? null,
            chartOfAccountsIdProvided: array_key_exists('chart_of_accounts_id', $validatedData),
            defaultConsolRateType: isset($validatedData['default_consol_rate_type'])
                ? strtoupper((string) $validatedData['default_consol_rate_type'])
                : null,
            defaultConsolRateTypeProvided: array_key_exists('default_consol_rate_type', $validatedData),
        );
    }
}
