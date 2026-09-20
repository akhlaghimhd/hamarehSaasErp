<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reactivate demo memberships without violating uq_tenant_users (tenant_id, user_id).
 *
 * Rule per (tenant_id, user_id):
 * - If an active row exists → only force status=1 (and is_owner for owner email); leave soft-deleted rows deleted.
 * - If only soft-deleted rows exist → restore the newest one to status=1, keep other deleted.
 */
class ReactivateDemoMembershipsSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasTable('tenant_users')) {
            $this->command?->warn('tables missing');

            return;
        }

        $emails = [
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

        $userIds = DB::table('users')
            ->whereIn('email', $emails)
            ->whereNull('deleted_at')
            ->pluck('user_id', 'email');

        $touched = 0;

        foreach ($userIds as $email => $userId) {
            DB::table('users')->where('user_id', $userId)->update([
                'status'     => 1,
                'updated_at' => now(),
            ]);

            $rows = DB::table('tenant_users')
                ->where('user_id', $userId)
                ->orderByDesc('is_owner')
                ->orderByDesc('updated_at')
                ->orderByDesc('created_at')
                ->get([
                    'tenant_user_id',
                    'tenant_id',
                    'user_id',
                    'status',
                    'is_owner',
                    'deleted_at',
                ]);

            // Group by tenant
            $byTenant = $rows->groupBy('tenant_id');

            foreach ($byTenant as $tenantId => $group) {
                $active = $group->first(fn ($r) => $r->deleted_at === null);
                $deleted = $group->filter(fn ($r) => $r->deleted_at !== null)->values();

                if ($active) {
                    // Keep existing active row; do NOT undelete siblings (unique constraint).
                    DB::table('tenant_users')
                        ->where('tenant_user_id', $active->tenant_user_id)
                        ->update([
                            'status'     => 1,
                            'is_owner'   => $email === 'owner@demo.local' ? true : (bool) $active->is_owner,
                            'updated_at' => now(),
                        ]);
                    $touched++;

                    // Ensure soft-deleted duplicates stay inactive/non-owner
                    foreach ($deleted as $d) {
                        DB::table('tenant_users')
                            ->where('tenant_user_id', $d->tenant_user_id)
                            ->update([
                                'status'     => 0,
                                'is_owner'   => false,
                                'updated_at' => now(),
                            ]);
                    }
                } elseif ($deleted->isNotEmpty()) {
                    // No active row: restore only the newest deleted membership
                    $restore = $deleted->first();
                    DB::table('tenant_users')
                        ->where('tenant_user_id', $restore->tenant_user_id)
                        ->update([
                            'status'     => 1,
                            'is_owner'   => $email === 'owner@demo.local',
                            'deleted_at' => null,
                            'updated_at' => now(),
                        ]);
                    $touched++;

                    foreach ($deleted->skip(1) as $d) {
                        DB::table('tenant_users')
                            ->where('tenant_user_id', $d->tenant_user_id)
                            ->update([
                                'status'     => 0,
                                'is_owner'   => false,
                                'updated_at' => now(),
                            ]);
                    }
                }
            }
        }

        if (Schema::hasTable('user_credentials')) {
            DB::table('user_credentials')->update([
                'failed_login_count' => 0,
                'locked_until'       => null,
            ]);
        }

        $this->command?->info("ReactivateDemoMemberships: membership rows touched={$touched}, users=".count($userIds));
        $this->command?->info('Login: owner@demo.local / Owner123!  |  staff *@demo.local / Staff123!');
    }
}
