<?php

namespace App\Modules\Organization\Contracts;

/**
 * Service Contract for Company lookup across modules.
 * Owned by Organization module.
 */
interface CompanyLookupContract
{
    public function findById(string $companyId): ?object;

    public function exists(string $companyId): bool;

    public function getBasicInfo(string $companyId): ?array;
}
