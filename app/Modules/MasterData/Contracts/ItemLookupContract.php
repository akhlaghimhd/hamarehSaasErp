<?php

namespace App\Modules\MasterData\Contracts;

/**
 * Service Contract for Item lookup across modules.
 * Owned by MasterData (Single Source of Truth).
 * Other modules must depend on this interface, not on Item model directly.
 */
interface ItemLookupContract
{
    public function findById(string $itemId): ?object;

    public function findByCode(string $code): ?object;

    public function exists(string $itemId): bool;

    public function getBasicInfo(string $itemId): ?array;
}
