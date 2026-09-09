<?php

namespace App\Modules\SaasAdmin\Contracts;

use App\Modules\SaasAdmin\Models\AdminUser;
use Illuminate\Support\Collection;

/**
 * L2-M08 – Service Contract for AdminUser operations.
 * Other modules / layers must depend on this interface, not the concrete service.
 */
interface AdminUserServiceContract
{
    public function create(
        string $username,
        string $email,
        string $password,
        ?string $firstName = null,
        ?string $lastName = null,
        ?string $mobile = null,
        int $status = 1
    ): AdminUser;

    public function update(
        string $adminUserId,
        ?string $firstName = null,
        ?string $lastName = null,
        ?string $mobile = null,
        ?int $status = null
    ): AdminUser;

    public function softDelete(string $adminUserId): void;

    public function list(): Collection;

    public function find(string $adminUserId): ?AdminUser;
}
