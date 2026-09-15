<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Seeds demo employees for the default demo tenant (UI table testing).
 * Safe to re-run: skips emails that already exist.
 *
 * IDs must be UUID — PostgreSQL uuid columns reject ULID strings.
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

        $members = [
            ['first_name' => 'سارا', 'last_name' => 'محمدی', 'email' => 'sara.mohammadi@demo.local', 'mobile' => '09121110001', 'status' => 1],
            ['first_name' => 'رضا', 'last_name' => 'کریمی', 'email' => 'reza.karimi@demo.local', 'mobile' => '09121110002', 'status' => 1],
            ['first_name' => 'مینا', 'last_name' => 'حسینی', 'email' => 'mina.hosseini@demo.local', 'mobile' => '09121110003', 'status' => 1],
            ['first_name' => 'علی', 'last_name' => 'رضایی', 'email' => 'ali.rezaei@demo.local', 'mobile' => '09121110004', 'status' => 1],
            ['first_name' => 'نگار', 'last_name' => 'احمدی', 'email' => 'negar.ahmadi@demo.local', 'mobile' => '09121110005', 'status' => 1],
            ['first_name' => 'حسین', 'last_name' => 'موسوی', 'email' => 'hossein.mousavi@demo.local', 'mobile' => '09121110006', 'status' => 1],
            ['first_name' => 'فاطمه', 'last_name' => 'جعفری', 'email' => 'fateme.jafari@demo.local', 'mobile' => '09121110007', 'status' => 1],
            ['first_name' => 'امیر', 'last_name' => 'کاظمی', 'email' => 'amir.kazemi@demo.local', 'mobile' => '09121110008', 'status' => 0],
            ['first_name' => 'زهرا', 'last_name' => 'نوری', 'email' => 'zahra.nouri@demo.local', 'mobile' => '09121110009', 'status' => 1],
            ['first_name' => 'مهدی', 'last_name' => 'صادقی', 'email' => 'mehdi.sadeghi@demo.local', 'mobile' => '09121110010', 'status' => 1],
            ['first_name' => 'لیلا', 'last_name' => 'اکبری', 'email' => 'leila.akbari@demo.local', 'mobile' => '09121110011', 'status' => 1],
            ['first_name' => 'پارسا', 'last_name' => 'حیدری', 'email' => 'parsa.heidari@demo.local', 'mobile' => '09121110012', 'status' => 1],
            ['first_name' => 'یاسمن', 'last_name' => 'باقری', 'email' => 'yasaman.bagheri@demo.local', 'mobile' => '09121110013', 'status' => 1],
            ['first_name' => 'کیان', 'last_name' => 'شریفی', 'email' => 'kian.sharifi@demo.local', 'mobile' => '09121110014', 'status' => 0],
            ['first_name' => 'نرگس', 'last_name' => 'طاهری', 'email' => 'narges.taheri@demo.local', 'mobile' => '09121110015', 'status' => 1],
        ];

        $created = 0;

        foreach ($members as $m) {
            $existingUser = DB::table('users')->where('email', $m['email'])->first();
            if ($existingUser) {
                $userId = $existingUser->user_id;
            } else {
                $userId = (string) Str::uuid();
                $userRow = [
                    'user_id'    => $userId,
                    'email'      => $m['email'],
                    'mobile'     => $m['mobile'],
                    'first_name' => $m['first_name'],
                    'last_name'  => $m['last_name'],
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
                'status'         => $m['status'],
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
