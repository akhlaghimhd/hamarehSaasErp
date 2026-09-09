<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * L1-09 – Base plans / plan versions for SaaS Platform Business (Layer 1).
 * Idempotent seed of a Free and a Standard plan with one version each.
 */
class SaasPlatformPlanSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $freePlanId = '10000000-0000-0000-0000-000000000001';
        $stdPlanId  = '10000000-0000-0000-0000-000000000002';
        $freeVerId  = '10000000-0000-0000-0000-000000000011';
        $stdVerId   = '10000000-0000-0000-0000-000000000012';

        DB::table('plans')->updateOrInsert(
            ['plan_id' => $freePlanId],
            [
                'code'       => 'FREE',
                'name'       => 'Free',
                'status'     => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'row_version'=> 1,
            ]
        );

        DB::table('plans')->updateOrInsert(
            ['plan_id' => $stdPlanId],
            [
                'code'       => 'STANDARD',
                'name'       => 'Standard',
                'status'     => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'row_version'=> 1,
            ]
        );

        DB::table('plan_versions')->updateOrInsert(
            ['plan_version_id' => $freeVerId],
            [
                'plan_id'        => $freePlanId,
                'version_number' => 1,
                'status'         => 1,
                'created_at'     => $now,
                'updated_at'     => $now,
                'row_version'    => 1,
            ]
        );

        DB::table('plan_versions')->updateOrInsert(
            ['plan_version_id' => $stdVerId],
            [
                'plan_id'        => $stdPlanId,
                'version_number' => 1,
                'status'         => 1,
                'created_at'     => $now,
                'updated_at'     => $now,
                'row_version'    => 1,
            ]
        );

        // Minimal price rows (billing_period_days = 30)
        foreach ([
            [$freeVerId, 0.0],
            [$stdVerId, 99.0],
        ] as [$verId, $amount]) {
            $exists = DB::table('plan_prices')
                ->where('plan_version_id', $verId)
                ->whereNull('deleted_at')
                ->exists();
            if (!$exists) {
                DB::table('plan_prices')->insert([
                    'plan_price_id'     => (string) Str::uuid(),
                    'plan_version_id'   => $verId,
                    'amount'            => $amount,
                    'billing_period_days' => 30,
                    'status'            => 1,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                    'row_version'       => 1,
                ]);
            }
        }
    }
}
