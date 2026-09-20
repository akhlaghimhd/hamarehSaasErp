<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reactivate demo memberships that were bulk-deactivated or soft-deleted by mistake.
 * Safe: only touches known @demo.local accounts + restores is_owner for owner@demo.local.
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

        $n = 0;
        foreach ($userIds as $email => $userId) {
            DB::table('users')->where('user_id', $userId)->update(['status' => 1, 'updated_at' => now()]);

            $updated = DB::table('tenant_users')->where('user_id', $userId)->update([
                'status'     => 1,
                'deleted_at' => null,
                'updated_at' => now(),
            ]);
            $n += (int) $updated;

            if ($email === 'owner@demo.local') {
                DB::table('tenant_users')
                    ->where('user_id', $userId)
                    ->whereNull('deleted_at')
                    ->update(['is_owner' => true, 'status' => 1, 'updated_at' => now()]);
            }
        }

        if (Schema::hasTable('user_credentials')) {
            DB::table('user_credentials')->update([
                'failed_login_count' => 0,
                'locked_until'       => null,
            ]);
        }

        $this->command?->info("ReactivateDemoMemberships: membership rows touched={$n}, users=".count($userIds));
        $this->command?->info('Login: owner@demo.local / Owner123!  |  staff *@demo.local / Staff123!');
    }
}
