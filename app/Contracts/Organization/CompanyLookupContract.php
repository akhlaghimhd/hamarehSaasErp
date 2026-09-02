<?php

namespace App\Contracts\Organization;

/**
 * Service Contract for Company lookup across modules.
 *
 * Used by almost every Layer 6 module that needs organizational context.
 * Implementation lives in Organization module.
 */
interface CompanyLookupContract
{
    /**
     * Find a company by its primary key.
     */
    public function findById(string $companyId): ?object;

    /**
     * Check existence for current tenant.
     */
    public function exists(string $companyId): bool;

    /**
     * Return basic info (id, code, name, status).
     */
    public function getBasicInfo(string $companyId): ?array;
}
