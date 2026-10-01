<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Full demo refresh for Identity + Organization QA.
 *
 * Large tenant (demo): rich org (≥5 per box) + ~260 users with mixed single/multi roles,
 * intentional SoD conflicts (direct DB assign bypasses assertAssignable).
 *
 * Small tenant (small-co): single company, 28 users, heavy multi-role (high conflict density).
 *
 *   docker compose exec app php artisan db:seed --class=PermissionSeeder
 *   docker compose exec app php artisan db:seed --class=TenantDefaultRbacSeeder
 *   docker compose exec app php artisan db:seed --class=FullDemoRefreshSeeder
 *
 * Login large: owner@demo.local / Owner123!
 * Login small: owner.small@demo.local / Owner123!
 * Staff password: Staff123!
 */
class FullDemoRefreshSeeder extends Seeder
{
    private const LARGE_TENANT_ID = '3ab77cac-1343-4b13-8e14-0d887aad132a';
    private const SYSTEM_TENANT_ID = '00000000-0000-0000-0000-000000000001';
    private const SMALL_TENANT_ID = 'a1111111-2222-3333-4444-555555555555';

    private const STAFF_PASSWORD = 'Staff123!';
    private const OWNER_PASSWORD = 'Owner123!';

    /** @var list<string> */
    private array $firstNames = [
        'علی', 'رضا', 'محمد', 'حسین', 'مهدی', 'امیر', 'سعید', 'جواد', 'حسن', 'مجید',
        'زهرا', 'فاطمه', 'مریم', 'سارا', 'نرگس', 'الهام', 'مینا', 'شیما', 'لیلا', 'نازنین',
        'کیان', 'آرش', 'پویا', 'نیما', 'سامان', 'بهرام', 'کامران', 'فرهاد', 'شهرام', 'داریوش',
    ];

    /** @var list<string> */
    private array $lastNames = [
        'محمدی', 'حسینی', 'رضایی', 'کریمی', 'موسوی', 'احمدی', 'جعفری', 'نوری', 'صادقی', 'اکبری',
        'کاظمی', 'شریفی', 'مرادی', 'یوسفی', 'باقری', 'طاهری', 'قاسمی', 'نجفی', 'زمانی', 'فرهادی',
    ];

    /**
     * Conflict pairs (role codes) for intentional SoD gaps — assigned via DB, not RoleService.
     *
     * @var list<array{0:string,1:string}>
     */
    private array $conflictPairs = [
        ['accountant', 'purchase'],
        ['accountant-senior', 'purchase-manager'],
        ['accountant-senior', 'cashier'],
        ['finance-manager', 'cashier'],
        ['sales', 'accountant-senior'],
        ['purchase', 'warehouse'],
        ['sales-lead', 'finance-manager'],
        ['identity-manager', 'access-reviewer'],
        ['identity-roles', 'identity-members'],
        ['sales', 'purchase'],
    ];

    public function run(): void
    {
        $this->command?->info('=== FullDemoRefreshSeeder START ===');

        $this->ensureTenants();

        $this->call(PermissionSeeder::class);
        $this->call(TenantDefaultRbacSeeder::class);
        $this->call(AccessCertPermissionSeeder::class);

        $this->purgeTenantIdentity(self::LARGE_TENANT_ID, keepOwnerEmail: 'owner@demo.local');
        $this->call(DemoTenantOwnerSeeder::class);
        $this->call(AriaSanatDemoOrgSeeder::class);
        $this->enableOrgFeaturePacks(self::LARGE_TENANT_ID);
        $this->seedMassUsers(self::LARGE_TENANT_ID, targetCount: 260, multiRoleBias: 0.35, conflictRate: 0.22);

        $this->purgeTenantIdentity(self::SMALL_TENANT_ID, keepOwnerEmail: 'owner.small@demo.local');
        $this->bootstrapSmallTenant();
        $this->seedMassUsers(self::SMALL_TENANT_ID, targetCount: 28, multiRoleBias: 0.95, conflictRate: 0.55, emailPrefix: 'small');

        $this->printSummary();
        $this->command?->info('=== FullDemoRefreshSeeder DONE ===');
        $this->command?->info('Large login: owner@demo.local / Owner123!');
        $this->command?->info('Small login: owner.small@demo.local / Owner123!');
        $this->command?->info('Staff password: Staff123!');
    }

    private function ensureTenants(): void
    {
        $this->call(TenantSeeder::class);

        DB::table('tenants')->updateOrInsert(
            ['tenant_id' => self::SMALL_TENANT_ID],
            [
                'tenant_code' => 'small-co',
                'tenant_name' => 'شرکت کوچک نمونه',
                'slug' => 'small-co',
                'tenant_type' => 1,
                'primary_domain_enabled' => false,
                'domain_status' => 1,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        if (Schema::hasTable('tenant_domains')) {
            DB::table('tenant_domains')->updateOrInsert(
                ['domain_name' => 'small.hamareherp.com'],
                [
                    'tenant_id' => self::SMALL_TENANT_ID,
                    'is_primary' => true,
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    private function enableOrgFeaturePacks(string $tenantId): void
    {
        try {
            $svc = app(\App\Modules\SaasPlatform\Services\FeatureCatalogService::class);
            foreach (['multi_company', 'multi_branch', 'multi_business_unit'] as $code) {
                $svc->setEntitlement($tenantId, $code, true, 'MANUAL', 'full demo refresh');
            }
        } catch (\Throwable $e) {
            $this->command?->warn('Feature pack enable skipped: '.$e->getMessage());
        }
    }

    private function purgeTenantIdentity(string $tenantId, string $keepOwnerEmail): void
    {
        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$tenantId]);

        if (Schema::hasTable('tenant_access_cert_items')) {
            DB::table('tenant_access_cert_items')->where('tenant_id', $tenantId)->delete();
        }
        if (Schema::hasTable('tenant_access_cert_campaigns')) {
            DB::table('tenant_access_cert_campaigns')->where('tenant_id', $tenantId)->delete();
        }
        if (Schema::hasTable('tenant_user_roles')) {
            DB::table('tenant_user_roles')->where('tenant_id', $tenantId)->delete();
        }
        if (Schema::hasTable('tenant_users')) {
            DB::table('tenant_users')->where('tenant_id', $tenantId)->delete();
        }

        $this->command?->info("Purged identity memberships for tenant {$tenantId}");
    }

    private function bootstrapSmallTenant(): void
    {
        $tenantId = self::SMALL_TENANT_ID;
        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$tenantId]);

        $permCount = (int) DB::table('tenant_permissions')->where('tenant_id', $tenantId)->whereNull('deleted_at')->count();
        if ($permCount === 0) {
            $source = DB::table('tenant_permissions')
                ->where('tenant_id', self::LARGE_TENANT_ID)
                ->whereNull('deleted_at')
                ->get();
            foreach ($source as $perm) {
                DB::table('tenant_permissions')->insert([
                    'tenant_permission_id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'code' => $perm->code,
                    'name' => $perm->name,
                    'module_name' => $perm->module_name,
                    'action_type' => $perm->action_type ?? null,
                    'description' => $perm->description ?? null,
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        try {
            app(\App\Modules\IdentityCore\Services\TenantRbacBootstrapService::class)->bootstrapTenant($tenantId);
        } catch (\Throwable $e) {
            $this->command?->warn('RBAC bootstrap small: '.$e->getMessage());
        }

        $userId = $this->upsertUser('owner.small@demo.local', '09120000001', 'مالک', 'شرکت‌کوچک');
        $this->upsertCredential($userId, self::OWNER_PASSWORD);
        $this->upsertMembership($tenantId, $userId, isOwner: true);
        $adminRoleId = $this->roleIdByCode($tenantId, 'tenant-admin');
        if ($adminRoleId) {
            $this->assignRoleDb($tenantId, $userId, $adminRoleId);
        }

        if (Schema::hasTable('erp_companies')) {
            $companyId = null;
            $existing = DB::table('erp_companies')
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->orderBy('created_at')
                ->first();
            if ($existing) {
                $companyId = (string) $existing->company_id;
                $upd = ['name' => 'شرکت کوچک نمونه', 'updated_at' => now()];
                if (Schema::hasColumn('erp_companies', 'is_primary')) {
                    $upd['is_primary'] = true;
                }
                if (Schema::hasColumn('erp_companies', 'legal_name')) {
                    $upd['legal_name'] = 'شرکت کوچک نمونه';
                }
                DB::table('erp_companies')->where('company_id', $companyId)->update($upd);
            } else {
                $companyId = (string) Str::uuid();
                $row = [
                    'company_id' => $companyId,
                    'tenant_id' => $tenantId,
                    'code' => 'HQ',
                    'name' => 'شرکت کوچک نمونه',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'row_version' => 1,
                ];
                if (Schema::hasColumn('erp_companies', 'legal_name')) {
                    $row['legal_name'] = 'شرکت کوچک نمونه';
                }
                if (Schema::hasColumn('erp_companies', 'is_primary')) {
                    $row['is_primary'] = true;
                }
                if (Schema::hasColumn('erp_companies', 'entity_kind')) {
                    $row['entity_kind'] = 'OPERATING';
                }
                if (Schema::hasColumn('erp_companies', 'status')) {
                    $row['status'] = 1;
                }
                DB::table('erp_companies')->insert($row);
            }

            if (Schema::hasTable('erp_branches') && $companyId) {
                $br = DB::table('erp_branches')
                    ->where('tenant_id', $tenantId)
                    ->where('company_id', $companyId)
                    ->whereNull('deleted_at')
                    ->first();
                if (!$br) {
                    $brRow = [
                        'branch_id' => (string) Str::uuid(),
                        'tenant_id' => $tenantId,
                        'company_id' => $companyId,
                        'code' => 'HQ',
                        'name' => 'دفتر مرکزی',
                        'is_active' => true,
                        'row_version' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    if (Schema::hasColumn('erp_branches', 'branch_kind')) {
                        $brRow['branch_kind'] = 'OFFICE';
                    }
                    DB::table('erp_branches')->insert($brRow);
                }
            }
        }

        $this->command?->info('Small tenant bootstrapped: '.$tenantId);
    }

    private function seedMassUsers(
        string $tenantId,
        int $targetCount,
        float $multiRoleBias,
        float $conflictRate,
        string $emailPrefix = 'staff'
    ): void {
        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$tenantId]);

        $roles = DB::table('tenant_roles')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where('status', 1)
            ->get(['tenant_role_id', 'code', 'name']);

        if ($roles->isEmpty()) {
            $this->command?->warn("No roles for {$tenantId} — skip mass users");

            return;
        }

        $roleByCode = [];
        $roleIds = [];
        foreach ($roles as $r) {
            $roleByCode[(string) $r->code] = (string) $r->tenant_role_id;
            $roleIds[] = (string) $r->tenant_role_id;
        }

        $i = 0;
        foreach ($roles as $r) {
            $i++;
            $code = (string) $r->code;
            if ($code === 'tenant-admin') {
                continue;
            }
            $email = sprintf('%s.%s.%03d@demo.local', $emailPrefix, preg_replace('/[^a-z0-9]+/i', '', $code), $i);
            $fn = $this->firstNames[$i % count($this->firstNames)];
            $ln = $this->lastNames[$i % count($this->lastNames)];
            $mobile = sprintf('09%09d', 120000000 + $i + (crc32($tenantId) % 100000));
            $userId = $this->upsertUser($email, $mobile, $fn, $ln.'-'.$code);
            $this->upsertCredential($userId, self::STAFF_PASSWORD);
            $this->upsertMembership($tenantId, $userId, isOwner: false);
            $this->assignRoleDb($tenantId, $userId, (string) $r->tenant_role_id);
        }

        $current = (int) DB::table('tenant_users')->where('tenant_id', $tenantId)->whereNull('deleted_at')->count();
        $created = 0;
        $conflictsForced = 0;

        for ($n = $current + 1; $n <= $targetCount; $n++) {
            $email = sprintf('%s.u%04d@demo.local', $emailPrefix, $n);
            $fn = $this->firstNames[$n % count($this->firstNames)];
            $ln = $this->lastNames[($n * 3) % count($this->lastNames)];
            $mobile = sprintf('09%09d', 200000000 + $n + (crc32($tenantId.$emailPrefix) % 50000));
            $userId = $this->upsertUser($email, $mobile, $fn, $ln);
            $this->upsertCredential($userId, self::STAFF_PASSWORD);
            $this->upsertMembership($tenantId, $userId, isOwner: false);

            $assigned = [];

            if (mt_rand() / mt_getrandmax() < $conflictRate) {
                $pair = $this->conflictPairs[$n % count($this->conflictPairs)];
                foreach ($pair as $code) {
                    if (isset($roleByCode[$code])) {
                        $this->assignRoleDb($tenantId, $userId, $roleByCode[$code]);
                        $assigned[$roleByCode[$code]] = true;
                    }
                }
                if (count($assigned) >= 2) {
                    $conflictsForced++;
                }
            }

            if ($assigned === []) {
                $rid = $roleIds[$n % count($roleIds)];
                $this->assignRoleDb($tenantId, $userId, $rid);
                $assigned[$rid] = true;
            }

            if (mt_rand() / mt_getrandmax() < $multiRoleBias) {
                $extra = mt_rand(1, min(4, count($roleIds)));
                shuffle($roleIds);
                $added = 0;
                foreach ($roleIds as $rid) {
                    if (isset($assigned[$rid])) {
                        continue;
                    }
                    $this->assignRoleDb($tenantId, $userId, $rid);
                    $assigned[$rid] = true;
                    $added++;
                    if ($added >= $extra) {
                        break;
                    }
                }
            }

            $created++;
        }

        $this->ensureAllPermissionsUsed($tenantId, $roleByCode);

        $members = (int) DB::table('tenant_users')->where('tenant_id', $tenantId)->whereNull('deleted_at')->count();
        $assignments = (int) DB::table('tenant_user_roles')->where('tenant_id', $tenantId)->whereNull('deleted_at')->count();
        $this->command?->info("Tenant {$tenantId}: members={$members} role_links={$assignments} forced_conflict_users≈{$conflictsForced} new≈{$created}");
    }

    /** @param array<string, string> $roleByCode */
    private function ensureAllPermissionsUsed(string $tenantId, array $roleByCode): void
    {
        if (!Schema::hasTable('tenant_role_permissions')) {
            return;
        }

        $allPermIds = DB::table('tenant_permissions')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where('status', 1)
            ->pluck('tenant_permission_id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $used = DB::table('tenant_role_permissions')
            ->where('tenant_id', $tenantId)
            ->pluck('tenant_permission_id')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->all();

        $missing = array_values(array_diff($allPermIds, $used));
        if ($missing === []) {
            return;
        }

        $targetRoleId = $roleByCode['tenant-admin']
            ?? $roleByCode['identity-manager']
            ?? (DB::table('tenant_roles')->where('tenant_id', $tenantId)->whereNull('deleted_at')->value('tenant_role_id'));

        if (!$targetRoleId) {
            return;
        }

        $rows = [];
        foreach ($missing as $pid) {
            $rows[] = [
                'tenant_role_permission_id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'tenant_role_id' => (string) $targetRoleId,
                'tenant_permission_id' => $pid,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('tenant_role_permissions')->insert($chunk);
        }
        $this->command?->info('Attached '.count($missing)." unused permissions to role {$targetRoleId}");
    }

    private function upsertUser(string $email, string $mobile, string $first, string $last): string
    {
        $existing = DB::table('users')->where('email', $email)->first();
        if ($existing) {
            DB::table('users')->where('user_id', $existing->user_id)->update([
                'mobile' => $mobile,
                'first_name' => $first,
                'last_name' => $last,
                'status' => 1,
                'updated_at' => now(),
            ]);

            return (string) $existing->user_id;
        }

        $userId = (string) Str::uuid();
        DB::table('users')->insert([
            'user_id' => $userId,
            'email' => $email,
            'mobile' => $mobile,
            'first_name' => $first,
            'last_name' => $last,
            'user_kind' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
            'row_version' => 1,
        ]);

        return $userId;
    }

    private function upsertCredential(string $userId, string $password): void
    {
        $hash = Hash::make($password);
        $existing = DB::table('user_credentials')->where('user_id', $userId)->first();
        if ($existing) {
            DB::table('user_credentials')->where('credential_id', $existing->credential_id)->update([
                'password_hash' => $hash,
                'is_verified' => true,
                'failed_login_count' => 0,
                'locked_until' => null,
                'updated_at' => now(),
            ]);

            return;
        }
        DB::table('user_credentials')->insert([
            'credential_id' => (string) Str::uuid(),
            'user_id' => $userId,
            'password_hash' => $hash,
            'authentication_type' => 1,
            'is_verified' => true,
            'two_factor_enabled' => false,
            'failed_login_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
            'row_version' => 1,
        ]);
    }

    private function upsertMembership(string $tenantId, string $userId, bool $isOwner): void
    {
        $existing = DB::table('tenant_users')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            DB::table('tenant_users')->where('tenant_user_id', $existing->tenant_user_id)->update([
                'is_owner' => $isOwner,
                'status' => 1,
                'deleted_at' => null,
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('tenant_users')->insert([
            'tenant_user_id' => (string) Str::uuid(),
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'is_owner' => $isOwner,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
            'row_version' => 1,
        ]);
    }

    private function roleIdByCode(string $tenantId, string $code): ?string
    {
        $id = DB::table('tenant_roles')
            ->where('tenant_id', $tenantId)
            ->where('code', $code)
            ->whereNull('deleted_at')
            ->value('tenant_role_id');

        return $id ? (string) $id : null;
    }

    private function assignRoleDb(string $tenantId, string $userId, string $roleId): void
    {
        $exists = DB::table('tenant_user_roles')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('tenant_role_id', $roleId)
            ->whereNull('deleted_at')
            ->exists();
        if ($exists) {
            return;
        }
        DB::table('tenant_user_roles')->insert([
            'tenant_user_role_id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'tenant_role_id' => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
            'row_version' => 1,
        ]);
    }

    private function printSummary(): void
    {
        foreach ([self::LARGE_TENANT_ID => 'LARGE', self::SMALL_TENANT_ID => 'SMALL'] as $tid => $label) {
            $members = Schema::hasTable('tenant_users')
                ? (int) DB::table('tenant_users')->where('tenant_id', $tid)->whereNull('deleted_at')->count()
                : 0;
            $roles = Schema::hasTable('tenant_roles')
                ? (int) DB::table('tenant_roles')->where('tenant_id', $tid)->whereNull('deleted_at')->count()
                : 0;
            $perms = Schema::hasTable('tenant_permissions')
                ? (int) DB::table('tenant_permissions')->where('tenant_id', $tid)->whereNull('deleted_at')->count()
                : 0;
            $companies = Schema::hasTable('erp_companies')
                ? (int) DB::table('erp_companies')->where('tenant_id', $tid)->whereNull('deleted_at')->count()
                : 0;
            $branches = Schema::hasTable('erp_branches')
                ? (int) DB::table('erp_branches')->where('tenant_id', $tid)->whereNull('deleted_at')->count()
                : 0;
            $this->command?->info("[{$label}] members={$members} roles={$roles} perms={$perms} companies={$companies} branches={$branches}");
        }
    }
}
