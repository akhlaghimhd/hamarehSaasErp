<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Removes demo staff memberships that were incorrectly attached to every tenant.
 * Keeps memberships only on the primary demo tenant (owner@demo.local's tenant).
 *
 *   docker compose exec app php artisan db:seed --class=CleanupCrossTenantDemoMembershipsSeeder
 */
class CleanupCrossTenantDemoMembershipsSeeder extends Seeder
{
    private const DEMO_EMAILS = [
        'owner@demo.local',
        'identity.manager@demo.local',
        'identity.members@demo.local',
        'identity.roles@demo.local',
        'finance.manager@demo.local',
        'finance.senior@demo.local',
        'finance.junior@demo.local',
        'sales.manager@demo.local',
        'sales.rep@demo.local',
        'purchase.manager@demo.local',
        'purchase.clerk@demo.local',
        'warehouse.manager@demo.local',
        'warehouse.clerk@demo.local',
        'ops.viewer@demo.local',
    ];

    public function run(): void
    {
        if (!Schema::hasTable('tenant_users') || !Schema::hasTable('users')) {
            $this->command?->error('Required tables missing.');

            return;
        }

        $keepTenantId = $this->resolvePrimaryDemoTenantId();
        if ($keepTenantId === null) {
            $this->command?->error('Could not resolve primary demo tenant (owner@demo.local membership).');

            return;
        }

        $this->command?->info("Primary demo tenant (keep memberships): {$keepTenantId}");

        $userIds = DB::table('users')
            ->whereIn('email', self::DEMO_EMAILS)
            ->pluck('user_id')
            ->map(fn ($id) => (string) $id)
            ->all();

        if ($userIds === []) {
            $this->command?->warn('No demo users found by email — nothing to clean.');

            return;
        }

        // Soft-delete (or hard-delete pivot-like rows) memberships of demo users on OTHER tenants
        $q = DB::table('tenant_users')
            ->whereIn('user_id', $userIds)
            ->where('tenant_id', '!=', $keepTenantId)
            ->whereNull('deleted_at');

        $count = (clone $q)->count();

        if ($count === 0) {
            $this->command?->info('No cross-tenant demo memberships found.');
        } else {
            $now = now();
            $updated = $q->update([
                'deleted_at' => $now,
                'updated_at' => $now,
                'status' => 0,
            ]);
            $this->command?->info("Soft-deleted {$updated} cross-tenant demo membership(s).");
        }

        // Report remaining memberships per tenant for demo users
        $rows = DB::table('tenant_users')
            ->whereIn('user_id', $userIds)
            ->whereNull('deleted_at')
            ->select('tenant_id', DB::raw('COUNT(*) as c'))
            ->groupBy('tenant_id')
            ->get();

        foreach ($rows as $row) {
            $this->command?->info("  tenant {$row->tenant_id}: {$row->c} active demo membership(s)");
        }

        // Also strip role assignments for soft-deleted memberships on other tenants
        if (Schema::hasTable('tenant_user_roles')) {
            $otherTenantIds = DB::table('tenant_users')
                ->whereIn('user_id', $userIds)
                ->where('tenant_id', '!=', $keepTenantId)
                ->pluck('tenant_id')
                ->unique()
                ->all();

            if ($otherTenantIds !== []) {
                $rolesRemoved = DB::table('tenant_user_roles')
                    ->whereIn('user_id', $userIds)
                    ->whereIn('tenant_id', $otherTenantIds)
                    ->delete();
                $this->command?->info("Removed {$rolesRemoved} role assignment(s) on non-primary tenants.");
            }
        }
    }

    private function resolvePrimaryDemoTenantId(): ?string
    {
        $fromEnv = env('DEMO_TENANT_ID');
        if (is_string($fromEnv) && $fromEnv !== '') {
            if (DB::table('tenants')->where('tenant_id', $fromEnv)->exists()) {
                return $fromEnv;
            }
        }

        $owner = DB::table('users')->where('email', 'owner@demo.local')->first();
        if ($owner) {
            $tid = DB::table('tenant_users')
                ->where('user_id', $owner->user_id)
                ->where('is_owner', true)
                ->whereNull('deleted_at')
                ->value('tenant_id');
            if ($tid) {
                return (string) $tid;
            }
            $tid = DB::table('tenant_users')
                ->where('user_id', $owner->user_id)
                ->whereNull('deleted_at')
                ->value('tenant_id');
            if ($tid) {
                return (string) $tid;
            }
        }

        $legacy = '3ab77cac-1343-4b13-8e14-0d887aad132a';
        if (DB::table('tenants')->where('tenant_id', $legacy)->exists()) {
            return $legacy;
        }

        return DB::table('tenants')->orderBy('created_at')->value('tenant_id');
    }
}
