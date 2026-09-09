<?php

namespace App\Modules\SaasAdmin\Contracts;

use App\Modules\SaasAdmin\Models\AdminUser;
use Illuminate\Database\Eloquent\Collection;

/**
 * L2-M08 – Service Contract for AdminUser operations.
 * Other modules / layers must depend on this interface, not the concrete service.
 */
interface AdminUserServiceContract
{
    public function list(): Collection;

    public function get(string $adminUserId): AdminUser;

    public function create(
        string $username,
        string $email,
        string $password,
        ?string $firstName = null,
        ?string $lastName = null,
        ?string $mobile = null,
        ?string $createdBy = null
    ): AdminUser;

    public function update(
        string $adminUserId,
        ?string $firstName = null,
        ?string $lastName = null,
        ?string $mobile = null,
        ?int $status = null,
        ?string $updatedBy = null
    ): AdminUser;

    public function softDelete(string $adminUserId, ?string $deletedBy = null): void;
}
