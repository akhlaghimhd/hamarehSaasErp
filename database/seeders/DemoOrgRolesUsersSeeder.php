<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Heavy QA dataset under the demo tenant owner:
 * - Key roles + child roles (hierarchy via parent_role_id when column exists)
 * - Permission assignment per role (only codes that exist in tenant_permissions)
 * - One dedicated user per role for login tests
 *
 * Prerequisites:
 *   php artisan db:seed --class=PermissionSeeder
 *   php artisan db:seed --class=DemoTenantOwnerSeeder
 *
 * Usage:
 *   docker compose exec app php artisan db:seed --class=DemoOrgRolesUsersSeeder
 *
 * Default password for all staff users: Staff123!
 */
class DemoOrgRolesUsersSeeder extends Seeder
{
    private const DEMO_TENANT_ID = '3ab77cac-1343-4b13-8e14-0d887aad132a';
    private const STAFF_PASSWORD = 'Staff123!';

    /** @var array<string, string> code => role_id */
    private array $roleIds = [];

    /** @var array<string, string> permission code => id */
    private array $permMap = [];

    private bool $hasParentColumn = false;

    public function run(): void
    {
        $tenantId = $this->resolveTenantId();
        if ($tenantId === null) {
            $this->command?->error('No tenant found. Seed tenants + PermissionSeeder first.');

            return;
        }

        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$tenantId]);

        $this->hasParentColumn = Schema::hasColumn('tenant_roles', 'parent_role_id');
        $this->permMap = $this->loadPermissionMap($tenantId);

        if ($this->permMap === []) {
            $this->command?->error('No permissions for tenant. Run: php artisan db:seed --class=PermissionSeeder');

            return;
        }

        $tree = $this->roleTree();

        // Pass 1: create parents then children
        foreach ($tree as $node) {
            $this->upsertRole($tenantId, $node, null);
            foreach ($node['children'] ?? [] as $child) {
                $this->upsertRole($tenantId, $child, $node['code']);
            }
        }

        // Pass 2: assign permissions
        foreach ($tree as $node) {
            $this->syncPermissions($tenantId, $node['code'], $node['permissions'] ?? []);
            foreach ($node['children'] ?? [] as $child) {
                $this->syncPermissions($tenantId, $child['code'], $child['permissions'] ?? []);
            }
        }

        // Pass 3: one user per role
        $created = [];
        foreach ($tree as $node) {
            $created[] = $this->ensureUserForRole($tenantId, $node);
            foreach ($node['children'] ?? [] as $child) {
                $created[] = $this->ensureUserForRole($tenantId, $child);
            }
        }

        $this->command?->info('Demo org structure ready on tenant '.$tenantId);
        $this->command?->info('Staff password for all role users: '.self::STAFF_PASSWORD);
        $this->command?->table(
            ['Role', 'Email', 'Mobile', 'Perms assigned'],
            array_map(static fn ($r) => [
                $r['role'],
                $r['email'],
                $r['mobile'],
                $r['perm_count'],
            ], $created)
        );
    }

    private function resolveTenantId(): ?string
    {
        $fromEnv = env('DEMO_TENANT_ID');
        if (is_string($fromEnv) && $fromEnv !== '' && DB::table('tenants')->where('tenant_id', $fromEnv)->exists()) {
            return $fromEnv;
        }

        if (DB::table('tenants')->where('tenant_id', self::DEMO_TENANT_ID)->exists()) {
            return self::DEMO_TENANT_ID;
        }

        return DB::table('tenants')->orderBy('created_at')->value('tenant_id');
    }

    /** @return array<string, string> */
    private function loadPermissionMap(string $tenantId): array
    {
        $map = [];
        $rows = DB::table('tenant_permissions')
            ->where('tenant_id', $tenantId)
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->get(['tenant_permission_id', 'code']);

        foreach ($rows as $row) {
            $map[(string) $row->code] = (string) $row->tenant_permission_id;
        }

        return $map;
    }

    /**
     * Hierarchical org roles for ERP testing under tenant owner.
     *
     * @return list<array<string, mixed>>
     */
    private function roleTree(): array
    {
        $identityBase = [
            'identity.profile.view',
            'identity.profile.update',
            'identity.user.view',
        ];

        return [
            [
                'code' => 'identity-manager',
                'name' => 'مدیر هویت و دسترسی',
                'description' => 'مدیریت کامل کاربران، نقش‌ها، مجوزها و محدوده دسترسی',
                'email' => 'identity.manager@demo.local',
                'mobile' => '09121000001',
                'first_name' => 'مدیر',
                'last_name' => 'هویت',
                'permissions' => array_merge($identityBase, [
                    'identity.user.create',
                    'identity.user.update',
                    'identity.user.delete',
                    'identity.user.restore',
                    'identity.role.view',
                    'identity.role.create',
                    'identity.role.update',
                    'identity.role.delete',
                    'identity.role.assign',
                    'identity.role.assign-permissions',
                    'identity.permission.view',
                    'identity.scope.view',
                    'identity.scope.create',
                    'identity.scope.update',
                    'identity.scope.delete',
                    'identity.scope.assign',
                    'identity.membership_history.view',
                ]),
                'children' => [
                    [
                        'code' => 'identity-members',
                        'name' => 'کارشناس اعضای سازمان',
                        'description' => 'افزودن و مدیریت وضعیت اعضا بدون مدیریت نقش',
                        'email' => 'identity.members@demo.local',
                        'mobile' => '09121000002',
                        'first_name' => 'کارشناس',
                        'last_name' => 'اعضا',
                        'permissions' => array_merge($identityBase, [
                            'identity.user.create',
                            'identity.user.update',
                            'identity.membership_history.view',
                        ]),
                    ],
                    [
                        'code' => 'identity-roles',
                        'name' => 'کارشناس نقش‌ها',
                        'description' => 'تعریف نقش و تخصیص مجوز به نقش؛ بدون حذف کاربر',
                        'email' => 'identity.roles@demo.local',
                        'mobile' => '09121000003',
                        'first_name' => 'کارشناس',
                        'last_name' => 'نقش',
                        'permissions' => array_merge($identityBase, [
                            'identity.role.view',
                            'identity.role.create',
                            'identity.role.update',
                            'identity.role.assign',
                            'identity.role.assign-permissions',
                            'identity.permission.view',
                        ]),
                    ],
                ],
            ],
            [
                'code' => 'finance-manager',
                'name' => 'مدیر مالی',
                'description' => 'نظارت و ثبت اسناد حسابداری',
                'email' => 'finance.manager@demo.local',
                'mobile' => '09121000011',
                'first_name' => 'مدیر',
                'last_name' => 'مالی',
                'permissions' => array_merge($identityBase, [
                    'accounting.voucher.view',
                    'accounting.voucher.post',
                    'accounting.account.view',
                    'procurement.payment-schedule.view',
                    'procurement.cash-transaction.view',
                    'procurement.cash-transaction.create',
                    'procurement.sales-invoice.view',
                    'procurement.purchase-invoice.view',
                ]),
                'children' => [
                    [
                        'code' => 'accountant-senior',
                        'name' => 'حسابدار ارشد',
                        'description' => 'ثبت و قطعی‌سازی اسناد',
                        'email' => 'finance.senior@demo.local',
                        'mobile' => '09121000012',
                        'first_name' => 'حسابدار',
                        'last_name' => 'ارشد',
                        'permissions' => array_merge($identityBase, [
                            'accounting.voucher.view',
                            'accounting.voucher.post',
                            'accounting.account.view',
                            'procurement.cash-transaction.view',
                        ]),
                    ],
                    [
                        'code' => 'accountant-junior',
                        'name' => 'حسابدار',
                        'description' => 'فقط مشاهده اسناد و حساب‌ها',
                        'email' => 'finance.junior@demo.local',
                        'mobile' => '09121000013',
                        'first_name' => 'حسابدار',
                        'last_name' => 'جونیور',
                        'permissions' => array_merge($identityBase, [
                            'accounting.voucher.view',
                            'accounting.account.view',
                        ]),
                    ],
                ],
            ],
            [
                'code' => 'sales-manager',
                'name' => 'مدیر فروش',
                'description' => 'مدیریت سفارش، حواله و فاکتور فروش',
                'email' => 'sales.manager@demo.local',
                'mobile' => '09121000021',
                'first_name' => 'مدیر',
                'last_name' => 'فروش',
                'permissions' => array_merge($identityBase, [
                    'procurement.sales-order.view',
                    'procurement.sales-order.create',
                    'procurement.sales-order.confirm',
                    'procurement.sales-quotation.create',
                    'procurement.sales-delivery.view',
                    'procurement.sales-delivery.create',
                    'procurement.sales-delivery.post',
                    'procurement.sales-invoice.view',
                    'procurement.sales-invoice.create',
                    'procurement.sales-invoice.update',
                    'procurement.sales-invoice.post',
                    'procurement.return-order.create',
                    'masterdata.business-partner.view',
                    'masterdata.business-partner.create',
                    'masterdata.business-partner.update',
                ]),
                'children' => [
                    [
                        'code' => 'sales-rep',
                        'name' => 'کارشناس فروش',
                        'description' => 'ثبت سفارش و پیش‌فاکتور بدون قطعی‌سازی فاکتور',
                        'email' => 'sales.rep@demo.local',
                        'mobile' => '09121000022',
                        'first_name' => 'کارشناس',
                        'last_name' => 'فروش',
                        'permissions' => array_merge($identityBase, [
                            'procurement.sales-order.view',
                            'procurement.sales-order.create',
                            'procurement.sales-quotation.create',
                            'procurement.sales-invoice.view',
                            'masterdata.business-partner.view',
                        ]),
                    ],
                ],
            ],
            [
                'code' => 'purchase-manager',
                'name' => 'مدیر خرید',
                'description' => 'درخواست، سفارش و رسید خرید',
                'email' => 'purchase.manager@demo.local',
                'mobile' => '09121000031',
                'first_name' => 'مدیر',
                'last_name' => 'خرید',
                'permissions' => array_merge($identityBase, [
                    'procurement.purchase-requisition.view',
                    'procurement.purchase-requisition.create',
                    'procurement.purchase-requisition.submit',
                    'procurement.purchase-requisition.approve',
                    'procurement.purchase-order.create',
                    'procurement.purchase-receipt.view',
                    'procurement.purchase-receipt.create',
                    'procurement.purchase-receipt.post',
                    'procurement.purchase-invoice.view',
                    'procurement.purchase-invoice.create',
                    'procurement.purchase-invoice.post',
                    'masterdata.business-partner.view',
                    'masterdata.business-partner.create',
                ]),
                'children' => [
                    [
                        'code' => 'purchase-clerk',
                        'name' => 'کارشناس خرید',
                        'description' => 'ثبت درخواست و سفارش بدون تأیید نهایی',
                        'email' => 'purchase.clerk@demo.local',
                        'mobile' => '09121000032',
                        'first_name' => 'کارشناس',
                        'last_name' => 'خرید',
                        'permissions' => array_merge($identityBase, [
                            'procurement.purchase-requisition.view',
                            'procurement.purchase-requisition.create',
                            'procurement.purchase-requisition.submit',
                            'procurement.purchase-order.create',
                            'procurement.purchase-receipt.view',
                            'masterdata.business-partner.view',
                        ]),
                    ],
                ],
            ],
            [
                'code' => 'warehouse-manager',
                'name' => 'مدیر انبار',
                'description' => 'کالا، انبار و اسناد موجودی',
                'email' => 'warehouse.manager@demo.local',
                'mobile' => '09121000041',
                'first_name' => 'مدیر',
                'last_name' => 'انبار',
                'permissions' => array_merge($identityBase, [
                    'inventory.item.view',
                    'inventory.item.create',
                    'inventory.warehouse.view',
                    'inventory.document.view',
                    'inventory.document.create',
                    'inventory.document.post',
                ]),
                'children' => [
                    [
                        'code' => 'warehouse-clerk',
                        'name' => 'انباردار',
                        'description' => 'ثبت اسناد موجودی بدون قطعی‌سازی',
                        'email' => 'warehouse.clerk@demo.local',
                        'mobile' => '09121000042',
                        'first_name' => 'انباردار',
                        'last_name' => 'عملیاتی',
                        'permissions' => array_merge($identityBase, [
                            'inventory.item.view',
                            'inventory.warehouse.view',
                            'inventory.document.view',
                            'inventory.document.create',
                        ]),
                    ],
                ],
            ],
            [
                'code' => 'operations-viewer',
                'name' => 'ناظر عملیات',
                'description' => 'دسترسی فقط‌خواندنی چندماژوله برای کنترل مدیریتی',
                'email' => 'ops.viewer@demo.local',
                'mobile' => '09121000051',
                'first_name' => 'ناظر',
                'last_name' => 'عملیات',
                'permissions' => array_merge($identityBase, [
                    'identity.role.view',
                    'identity.permission.view',
                    'identity.membership_history.view',
                    'accounting.voucher.view',
                    'accounting.account.view',
                    'inventory.item.view',
                    'inventory.warehouse.view',
                    'inventory.document.view',
                    'procurement.sales-order.view',
                    'procurement.sales-invoice.view',
                    'procurement.purchase-receipt.view',
                    'procurement.purchase-invoice.view',
                    'masterdata.business-partner.view',
                    'workflow.instance.view',
                    'workflow.task.view',
                ]),
                'children' => [],
            ],
        ];
    }

    /** @param array<string, mixed> $def */
    private function upsertRole(string $tenantId, array $def, ?string $parentCode): void
    {
        $code = $def['code'];
        $parentId = null;
        if ($parentCode !== null && $this->hasParentColumn && isset($this->roleIds[$parentCode])) {
            $parentId = $this->roleIds[$parentCode];
        }

        $existing = DB::table('tenant_roles')
            ->where('tenant_id', $tenantId)
            ->where('code', $code)
            ->whereNull('deleted_at')
            ->first();

        $payload = [
            'name' => $def['name'],
            'description' => $def['description'] ?? null,
            'status' => 1,
            'updated_at' => now(),
        ];

        if ($this->hasParentColumn) {
            $payload['parent_role_id'] = $parentId;
        }

        if ($existing) {
            DB::table('tenant_roles')->where('tenant_role_id', $existing->tenant_role_id)->update($payload);
            $this->roleIds[$code] = (string) $existing->tenant_role_id;

            return;
        }

        $roleId = (string) Str::uuid();
        $insert = [
            'tenant_role_id' => $roleId,
            'tenant_id' => $tenantId,
            'code' => $code,
            'name' => $def['name'],
            'description' => $def['description'] ?? null,
            'status' => 1,
            'is_system_default' => false,
            'created_at' => now(),
            'updated_at' => now(),
            'row_version' => 1,
        ];
        if ($this->hasParentColumn) {
            $insert['parent_role_id'] = $parentId;
        }

        DB::table('tenant_roles')->insert($insert);
        $this->roleIds[$code] = $roleId;
    }

    /** @param list<string> $codes */
    private function syncPermissions(string $tenantId, string $roleCode, array $codes): int
    {
        $roleId = $this->roleIds[$roleCode] ?? null;
        if (!$roleId) {
            return 0;
        }

        DB::table('tenant_role_permissions')
            ->where('tenant_id', $tenantId)
            ->where('tenant_role_id', $roleId)
            ->delete();

        $rows = [];
        $seen = [];
        foreach ($codes as $code) {
            if (!isset($this->permMap[$code]) || isset($seen[$code])) {
                continue;
            }
            $seen[$code] = true;
            $rows[] = [
                'tenant_role_permission_id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'tenant_role_id' => $roleId,
                'tenant_permission_id' => $this->permMap[$code],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows !== []) {
            DB::table('tenant_role_permissions')->insert($rows);
        }

        return count($rows);
    }

    /**
     * @param  array<string, mixed>  $def
     * @return array{role: string, email: string, mobile: string, perm_count: int}
     */
    private function ensureUserForRole(string $tenantId, array $def): array
    {
        $email = $def['email'];
        $mobile = $def['mobile'];
        $roleCode = $def['code'];
        $roleId = $this->roleIds[$roleCode];

        $userId = $this->upsertUser(
            $email,
            $mobile,
            $def['first_name'] ?? 'کاربر',
            $def['last_name'] ?? $def['name']
        );
        $this->upsertCredential($userId);
        $this->upsertMembership($tenantId, $userId);

        $exists = DB::table('tenant_user_roles')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('tenant_role_id', $roleId)
            ->whereNull('deleted_at')
            ->exists();

        if (!$exists) {
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

        $permCount = (int) DB::table('tenant_role_permissions')
            ->where('tenant_id', $tenantId)
            ->where('tenant_role_id', $roleId)
            ->count();

        return [
            'role' => $def['name'].' ('.$roleCode.')',
            'email' => $email,
            'mobile' => $mobile,
            'perm_count' => $permCount,
        ];
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

    private function upsertCredential(string $userId): void
    {
        $hash = Hash::make(self::STAFF_PASSWORD);
        $existing = DB::table('user_credentials')->where('user_id', $userId)->first();
        if ($existing) {
            DB::table('user_credentials')->where('credential_id', $existing->credential_id)->update([
                'password_hash' => $hash,
                'authentication_type' => 1,
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

    private function upsertMembership(string $tenantId, string $userId): void
    {
        $existing = DB::table('tenant_users')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->first();

        if ($existing) {
            DB::table('tenant_users')->where('tenant_user_id', $existing->tenant_user_id)->update([
                'is_owner' => false,
                'status' => 1,
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('tenant_users')->insert([
            'tenant_user_id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'is_owner' => false,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
            'row_version' => 1,
        ]);
    }
}
