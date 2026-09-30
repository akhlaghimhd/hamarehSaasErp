<?php

namespace Database\Seeders;

use App\Modules\IdentityCore\Services\TenantRbacBootstrapService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds default role catalog + active SoD rules for all tenants (or one via --tenant=UUID env).
 *
 *   docker compose exec app php artisan db:seed --class=TenantDefaultRbacSeeder
 *   docker compose exec app php artisan db:seed --class=PermissionSeeder
 */
class TenantDefaultRbacSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('tenants') || !Schema::hasTable('tenant_roles')) {
            $this->command?->error('Required tables missing.');

            return;
        }

        $only = env('SEED_TENANT_ID');
        $tenantIds = $only
            ? [(string) $only]
            : DB::table('tenants')->pluck('tenant_id')->map(fn ($id) => (string) $id)->all();

        if ($tenantIds === []) {
            $this->command?->error('No tenants found.');

            return;
        }

        $force = filter_var(env('SEED_RBAC_FORCE_PERMS', false), FILTER_VALIDATE_BOOLEAN);
        $svc = app(TenantRbacBootstrapService::class);

        foreach ($tenantIds as $tenantId) {
            $result = $svc->bootstrapTenant($tenantId, $force);
            $this->command?->info(
                "Tenant {$tenantId}: roles+={$result['roles_created']} roles~={$result['roles_updated']} sod+={$result['sod_created']} skipped=".implode(',', $result['skipped_roles'])
            );
        }
    }
}
