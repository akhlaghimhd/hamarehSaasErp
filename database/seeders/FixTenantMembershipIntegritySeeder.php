<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-shot integrity repair for tenant memberships:
 * - Soft-deleted rows must not keep is_owner / active status
 * - At most one active (status=1, deleted_at null) membership per (tenant_id, user_id)
 * - Extra concurrent owners on same tenant reduced to one
 */
class FixTenantMembershipIntegritySeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('tenant_users')) {
            $this->command?->warn('tenant_users missing');

            return;
        }

        $n1 = DB::table('tenant_users')
            ->whereNotNull('deleted_at')
            ->where(function ($q) {
                $q->where('is_owner', true)->orWhere('status', '!=', 0);
            })
            ->update([
                'is_owner'   => false,
                'status'     => 0,
                'updated_at' => now(),
            ]);

        $dupes = DB::table('tenant_users')
            ->select('tenant_id', 'user_id', DB::raw('COUNT(*) as c'))
            ->whereNull('deleted_at')
            ->where('status', 1)
            ->groupBy('tenant_id', 'user_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $n2 = 0;
        foreach ($dupes as $d) {
            $rows = DB::table('tenant_users')
                ->where('tenant_id', $d->tenant_id)
                ->where('user_id', $d->user_id)
                ->whereNull('deleted_at')
                ->where('status', 1)
                ->orderByDesc('is_owner')
                ->orderByDesc('updated_at')
                ->orderByDesc('created_at')
                ->get(['tenant_user_id']);

            foreach ($rows as $i => $r) {
                if ($i === 0) {
                    continue;
                }
                DB::table('tenant_users')
                    ->where('tenant_user_id', $r->tenant_user_id)
                    ->update([
                        'status'     => 0,
                        'is_owner'   => false,
                        'deleted_at' => now(),
                        'updated_at' => now(),
                    ]);
                $n2++;
            }
        }

        $ownerDupes = DB::table('tenant_users')
            ->select('tenant_id', DB::raw('COUNT(*) as c'))
            ->whereNull('deleted_at')
            ->where('status', 1)
            ->where('is_owner', true)
            ->groupBy('tenant_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $n3 = 0;
        foreach ($ownerDupes as $d) {
            $rows = DB::table('tenant_users')
                ->where('tenant_id', $d->tenant_id)
                ->whereNull('deleted_at')
                ->where('status', 1)
                ->where('is_owner', true)
                ->orderByDesc('updated_at')
                ->get(['tenant_user_id']);
            foreach ($rows as $i => $r) {
                if ($i === 0) {
                    continue;
                }
                DB::table('tenant_users')
                    ->where('tenant_user_id', $r->tenant_user_id)
                    ->update(['is_owner' => false, 'updated_at' => now()]);
                $n3++;
            }
        }

        $this->command?->info("FixTenantMembershipIntegrity: soft-deleted cleaned={$n1}, duplicate memberships soft-deleted={$n2}, extra owners cleared={$n3}");
        $this->command?->info('Users should re-login. Soft-deleted memberships cannot log in or grant owner.');
    }
}
