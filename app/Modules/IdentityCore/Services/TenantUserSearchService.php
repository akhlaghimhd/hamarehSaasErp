<?php

namespace App\Modules\IdentityCore\Services;

use App\Modules\IdentityCore\Models\TenantUser;
use App\Base\Services\HoldingAccessService;
use Illuminate\Database\Eloquent\Collection;
use Exception;

/**
 * Lightweight typeahead search for tenant members (privileged-access form, etc.).
 */
class TenantUserSearchService
{
    public function search(string $term, int $limit = 25, string $membershipFilter = 'active'): Collection
    {
        $tenantId = $this->getTenantId();
        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return new Collection();
        }

        $limit = max(1, min($limit, 50));
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';

        $query = TenantUser::query()
            ->where('tenant_users.tenant_id', $tenantId)
            ->with(['user:user_id,first_name,last_name,email,mobile,status,user_kind,created_at']);

        if ($membershipFilter === 'deleted') {
            $query->onlyTrashed();
        }

        app(HoldingAccessService::class)->constrainTenantUsersQuery($query);

        return $query
            ->whereHas('user', function ($uq) use ($like) {
                $uq->where(function ($w) use ($like) {
                    $w->where('mobile', 'ilike', $like)
                        ->orWhere('email', 'ilike', $like)
                        ->orWhere('first_name', 'ilike', $like)
                        ->orWhere('last_name', 'ilike', $like)
                        ->orWhereRaw(
                            "concat_ws(' ', coalesce(first_name, ''), coalesce(last_name, '')) ilike ?",
                            [$like]
                        );
                });
            })
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    private function getTenantId(): string
    {
        $tenantId = app()->bound('current_tenant_id') ? app('current_tenant_id') : null;
        if (!$tenantId) {
            throw new Exception('Tenant Context is missing. Architecture Violation.');
        }

        return (string) $tenantId;
    }
}
