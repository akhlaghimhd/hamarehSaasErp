<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Seeds 100 demo employees for the default demo tenant (UI stress testing).
 * Safe to re-run: skips emails that already exist.
 * IDs must be UUID.
 */
class DemoTenantMembersSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = '3ab77cac-1343-4b13-8e14-0d887aad132a';

        if (!Schema::hasTable('users') || !Schema::hasTable('tenant_users')) {
            $this->command?->warn('users/tenant_users missing — skip DemoTenantMembersSeeder');
            return;
        }

        $passwordHash = Hash::make('Secret123!');

        $firstNames = [
            'سارا', 'رضا', 'مینا', 'علی', 'نگار', 'حسین', 'فاطمه', 'امیر', 'زهرا', 'مهدی',
            'لیلا', 'پارسا', 'یاسمن', 'کیان', 'نرگس', 'آرمین', 'هانیه', 'سینا', 'مریم', 'پویا',
            'نیلوفر', 'آرمان', 'شیدا', 'کامران', 'پریسا',
        ];
        $lastNames = [
            'محمدی', 'کریمی', 'حسینی', 'رضایی', 'احمدی', 'موسوی', 'جعفری', 'کاظمی', 'نوری', 'صادقی',
            'اکبری', 'حیدری', 'باقری', 'شریفی', 'طاهری', 'مرادی', 'قاسمی', 'نجفی', 'رحیمی', 'عباسی',
        ];

        $created = 0;

        for ($i = 1; $i <= 100; $i++) {
            $email = sprintf('emp%03d@demo.local', $i);
            $mobile = sprintf('0912%07d', $i);
            if (strlen($mobile) > 11) {
                $mobile = substr($mobile, 0, 11);
            }
            $first = $firstNames[($i - 1) % count($firstNames)];
            $last = $lastNames[($i - 1) % count($lastNames)];
            $status = ($i % 12 === 0) ? 0 : 1;

            $existingUser = DB::table('users')->where('email', $email)->first();
            if ($existingUser) {
                $userId = $existingUser->user_id;
            } else {
                $userId = (string) Str::uuid();
                $userRow = [
                    'user_id'    => $userId,
                    'email'      => $email,
                    'mobile'     => $mobile,
                    'first_name' => $first,
                    'last_name'  => $last,
                    'status'     => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $userRow = array_filter(
                    $userRow,
                    fn ($k) => Schema::hasColumn('users', $k),
                    ARRAY_FILTER_USE_KEY
                );
                DB::table('users')->insert($userRow);

                if (Schema::hasTable('user_credentials')) {
                    $cred = [
                        'user_id'            => $userId,
                        'password_hash'      => $passwordHash,
                        'failed_login_count' => 0,
                        'locked_until'       => null,
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ];
                    if (Schema::hasColumn('user_credentials', 'credential_id')) {
                        $cred['credential_id'] = (string) Str::uuid();
                    }
                    if (Schema::hasColumn('user_credentials', 'user_credential_id')) {
                        $cred['user_credential_id'] = (string) Str::uuid();
                    }
                    if (Schema::hasColumn('user_credentials', 'authentication_type')) {
                        $cred['authentication_type'] = 1;
                    }
                    $cred = array_filter(
                        $cred,
                        fn ($k) => Schema::hasColumn('user_credentials', $k),
                        ARRAY_FILTER_USE_KEY
                    );
                    DB::table('user_credentials')->insert($cred);
                }
            }

            $membershipExists = DB::table('tenant_users')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->whereNull('deleted_at')
                ->exists();

            if ($membershipExists) {
                continue;
            }

            $tu = [
                'tenant_user_id' => (string) Str::uuid(),
                'tenant_id'      => $tenantId,
                'user_id'        => $userId,
                'is_owner'       => false,
                'status'         => $status,
                'created_at'     => now(),
                'updated_at'     => now(),
            ];
            if (Schema::hasColumn('tenant_users', 'row_version')) {
                $tu['row_version'] = 1;
            }
            $tu = array_filter(
                $tu,
                fn ($k) => Schema::hasColumn('tenant_users', $k),
                ARRAY_FILTER_USE_KEY
            );
            DB::table('tenant_users')->insert($tu);
            $created++;
        }

        $this->command?->info("DemoTenantMembersSeeder: {$created} memberships created for demo tenant.");
    }
}
