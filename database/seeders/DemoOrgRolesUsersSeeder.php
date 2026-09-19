<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Heavy QA dataset: hierarchical roles + permissions + one user per role.
 * Runs for EVERY tenant that already has memberships (fixes empty UI when login tenant ≠ legacy demo UUID).
 *
 *   docker compose exec app php artisan db:seed --class=PermissionSeeder
 *   docker compose exec app php artisan db:seed --class=DemoOrgRolesUsersSeeder
 *
 * Staff password: Staff123!
 */
class DemoOrgRolesUsersSeeder extends Seeder
{
    private const STAFF_PASSWORD = 'Staff123!';

    /** @var array<string, string> */
    private array $roleIds = [];

    /** @var array<string, string> */
    private array $permMap = [];

    private bool $hasParentColumn = false;

    public function run(): void
    {
        if (!Schema::hasTable('tenants') || !Schema::hasTable('tenant_roles')) {
            $this->command?->error('Required tables missing.');

            return;
        }

        $this->hasParentColumn = Schema::hasColumn('tenant_roles', 'parent_role_id');

        $tenantIds = $this->resolveTenantIds();
        if ($tenantIds === []) {
            $this->command?->error('No tenants found.');

            return;
        }

        $this->printDiagnostics($tenantIds);

        // Ensure permission catalog exists somewhere, then clone to each tenant
        $this->ensureCatalogOnAllTenants($tenantIds);

        foreach ($tenantIds as $tenantId) {
            $this->roleIds = [];
            DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$tenantId]);

            $this->permMap = $this->loadPermissionMap($tenantId);
            if ($this->permMap === []) {
                $this->command?->warn("Tenant {$tenantId}: still 0 permissions after catalog sync — skip roles.");
                continue;
            }

            $tree = $this->roleTree();

            foreach ($tree as $node) {
                $this->upsertRole($tenantId, $node, null);
                foreach ($node['children'] ?? [] as $child) {
                    $this->upsertRole($tenantId, $child, $node['code']);
                }
            }

            // Keep tenant-admin as full catalog (owner)
            $this->ensureTenantAdminFullAccess($tenantId);

            foreach ($tree as $node) {
                $this->syncPermissions($tenantId, $node['code'], $node['permissions'] ?? []);
                foreach ($node['children'] ?? [] as $child) {
                    $this->syncPermissions($tenantId, $child['code'], $child['permissions'] ?? []);
                }
            }

            $created = [];
            foreach ($tree as $node) {
                $created[] = $this->ensureUserForRole($tenantId, $node);
                foreach ($node['children'] ?? [] as $child) {
                    $created[] = $this->ensureUserForRole($tenantId, $child);
                }
            }

            $roleCount = (int) DB::table('tenant_roles')->where('tenant_id', $tenantId)->whereNull('deleted_at')->count();
            $permCount = count($this->permMap);
            $this->command?->info("Tenant {$tenantId}: roles={$roleCount} perms={$permCount} staff_users=".count($created));
        }

        $this->command?->info('Staff password for all role users: '.self::STAFF_PASSWORD);
        $this->command?->info('Re-login after seed. Owner: owner@demo.local / Owner123!');
    }

    /** @return list<string> */
    private function resolveTenantIds(): array
    {
        $ids = DB::table('tenants')->pluck('tenant_id')->map(fn ($id) => (string) $id)->all();

        // Prefer tenants that already have members (active login targets)
        if (Schema::hasTable('tenant_users')) {
            $withMembers = DB::table('tenant_users')
                ->whereNull('deleted_at')
                ->distinct()
                ->pluck('tenant_id')
                ->map(fn ($id) => (string) $id)
                ->all();

            if ($withMembers !== []) {
                // Union: members tenants first, then any remaining
                $ids = array_values(array_unique(array_merge($withMembers, $ids)));
            }
        }

        return $ids;
    }

    /** @param list<string> $tenantIds */
    private function printDiagnostics(array $tenantIds): void
    {
        $this->command?->info('--- Identity data diagnostic (before seed) ---');
        foreach ($tenantIds as $tid) {
            $roles = Schema::hasTable('tenant_roles')
                ? (int) DB::table('tenant_roles')->where('tenant_id', $tid)->whereNull('deleted_at')->count()
                : 0;
            $perms = Schema::hasTable('tenant_permissions')
                ? (int) DB::table('tenant_permissions')->where('tenant_id', $tid)->whereNull('deleted_at')->count()
                : 0;
            $members = Schema::hasTable('tenant_users')
                ? (int) DB::table('tenant_users')->where('tenant_id', $tid)->whereNull('deleted_at')->count()
                : 0;
            $owners = Schema::hasTable('tenant_users')
                ? (int) DB::table('tenant_users')->where('tenant_id', $tid)->where('is_owner', true)->whereNull('deleted_at')->count()
                : 0;
            $this->command?->info("  {$tid}  roles={$roles}  perms={$perms}  members={$members}  owners={$owners}");
        }
        $this->command?->info('-----------------------------------------------');
    }

    /** @param list<string> $tenantIds */
    private function ensureCatalogOnAllTenants(array $tenantIds): void
    {
        if (!Schema::hasTable('tenant_permissions')) {
            return;
        }

        $sourceTenantId = DB::table('tenant_permissions')
            ->select('tenant_id', DB::raw('COUNT(*) as c'))
            ->groupBy('tenant_id')
            ->orderByDesc('c')
            ->value('tenant_id');

        if (!$sourceTenantId) {
            // bootstrap minimal identity on first tenant
            $first = $tenantIds[0];
            DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$first]);
            $this->bootstrapMinimalIdentity($first);
            $sourceTenantId = $first;
        }

        $sourcePerms = DB::table('tenant_permissions')
            ->where('tenant_id', $sourceTenantId)
            ->whereNull('deleted_at')
            ->get();

        if ($sourcePerms->isEmpty()) {
            $this->bootstrapMinimalIdentity((string) $sourceTenantId);
            $sourcePerms = DB::table('tenant_permissions')
                ->where('tenant_id', $sourceTenantId)
                ->whereNull('deleted_at')
                ->get();
        }

        foreach ($tenantIds as $tenantId) {
            if ($tenantId === (string) $sourceTenantId) {
                continue;
            }
            DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$tenantId]);
            foreach ($sourcePerms as $perm) {
                $exists = DB::table('tenant_permissions')
                    ->where('tenant_id', $tenantId)
                    ->where('code', $perm->code)
                    ->exists();
                if ($exists) {
                    continue;
                }
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
    }

    private function bootstrapMinimalIdentity(string $tenantId): void
    {
        $minimal = [
            ['code' => 'identity.user.view', 'name' => 'مشاهده کاربران', 'module_name' => 'هویت و دسترسی', 'action_type' => 'READ'],
            ['code' => 'identity.user.create', 'name' => 'ایجاد کاربر', 'module_name' => 'هویت و دسترسی', 'action_type' => 'CREATE'],
            ['code' => 'identity.user.update', 'name' => 'ویرایش کاربر', 'module_name' => 'هویت و دسترسی', 'action_type' => 'UPDATE'],
            ['code' => 'identity.user.delete', 'name' => 'حذف کاربر', 'module_name' => 'هویت و دسترسی', 'action_type' => 'DELETE'],
            ['code' => 'identity.role.view', 'name' => 'مشاهده نقش‌ها', 'module_name' => 'هویت و دسترسی', 'action_type' => 'READ'],
            ['code' => 'identity.role.create', 'name' => 'ایجاد نقش', 'module_name' => 'هویت و دسترسی', 'action_type' => 'CREATE'],
            ['code' => 'identity.role.update', 'name' => 'ویرایش نقش', 'module_name' => 'هویت و دسترسی', 'action_type' => 'UPDATE'],
            ['code' => 'identity.role.delete', 'name' => 'حذف نقش', 'module_name' => 'هویت و دسترسی', 'action_type' => 'DELETE'],
            ['code' => 'identity.role.assign', 'name' => 'تخصیص نقش', 'module_name' => 'هویت و دسترسی', 'action_type' => 'EXECUTE'],
            ['code' => 'identity.role.assign-permissions', 'name' => 'تخصیص مجوز به نقش', 'module_name' => 'هویت و دسترسی', 'action_type' => 'EXECUTE'],
            ['code' => 'identity.permission.view', 'name' => 'مشاهده مجوزها', 'module_name' => 'هویت و دسترسی', 'action_type' => 'READ'],
            ['code' => 'identity.scope.view', 'name' => 'مشاهده محدوده دسترسی', 'module_name' => 'هویت و دسترسی', 'action_type' => 'READ'],
            ['code' => 'identity.profile.view', 'name' => 'مشاهده پروفایل', 'module_name' => 'هویت و دسترسی', 'action_type' => 'READ'],
            ['code' => 'identity.profile.update', 'name' => 'ویرایش پروفایل', 'module_name' => 'هویت و دسترسی', 'action_type' => 'UPDATE'],
            ['code' => 'identity.membership_history.view', 'name' => 'مشاهده تاریخچه عضویت', 'module_name' => 'هویت و دسترسی', 'action_type' => 'READ'],
        ];

        foreach ($minimal as $perm) {
            if (DB::table('tenant_permissions')->where('tenant_id', $tenantId)->where('code', $perm['code'])->exists()) {
                continue;
            }
            DB::table('tenant_permissions')->insert([
                'tenant_permission_id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'code' => $perm['code'],
                'name' => $perm['name'],
                'module_name' => $perm['module_name'],
                'action_type' => $perm['action_type'],
                'description' => $perm['name'],
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function ensureTenantAdminFullAccess(string $tenantId): void
    {
        $role = DB::table('tenant_roles')
            ->where('tenant_id', $tenantId)
            ->where('code', 'tenant-admin')
            ->whereNull('deleted_at')
            ->first();

        if (!$role) {
            $roleId = (string) Str::uuid();
            $insert = [
                'tenant_role_id' => $roleId,
                'tenant_id' => $tenantId,
                'code' => 'tenant-admin',
                'name' => 'مدیر سازمان',
                'description' => 'دسترسی کامل به تمام قابلیت‌های سازمان',
                'status' => 1,
                'is_system_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
                'row_version' => 1,
            ];
            if ($this->hasParentColumn) {
                $insert['parent_role_id'] = null;
            }
            DB::table('tenant_roles')->insert($insert);
        } else {
            $roleId = (string) $role->tenant_role_id;
            DB::table('tenant_roles')->where('tenant_role_id', $roleId)->update([
                'name' => 'مدیر سازمان',
                'status' => 1,
                'updated_at' => now(),
            ]);
        }

        $this->roleIds['tenant-admin'] = $roleId;

        DB::table('tenant_role_permissions')
            ->where('tenant_id', $tenantId)
            ->where('tenant_role_id', $roleId)
            ->delete();

        $rows = [];
        foreach ($this->permMap as $pid) {
            $rows[] = [
                'tenant_role_permission_id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'tenant_role_id' => $roleId,
                'tenant_permission_id' => $pid,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        if ($rows !== []) {
            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table('tenant_role_permissions')->insert($chunk);
            }
        }

        // Assign to all owners of this tenant
        if (!Schema::hasTable('tenant_users')) {
            return;
        }
        $owners = DB::table('tenant_users')
            ->where('tenant_id', $tenantId)
            ->where('is_owner', true)
            ->whereNull('deleted_at')
            ->pluck('user_id');

        foreach ($owners as $userId) {
            $exists = DB::table('tenant_user_roles')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->where('tenant_role_id', $roleId)
                ->whereNull('deleted_at')
                ->exists();
            if ($exists) {
                continue;
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

    /** @return list<array<string, mixed>> */
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
                    'identity.user.create', 'identity.user.update', 'identity.user.delete', 'identity.user.restore',
                    'identity.role.view', 'identity.role.create', 'identity.role.update', 'identity.role.delete',
                    'identity.role.assign', 'identity.role.assign-permissions',
                    'identity.permission.view',
                    'identity.scope.view', 'identity.scope.create', 'identity.scope.update', 'identity.scope.delete', 'identity.scope.assign',
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
                            'identity.user.create', 'identity.user.update', 'identity.membership_history.view',
                        ]),
                    ],
                    [
                        'code' => 'identity-roles',
                        'name' => 'کارشناس نقش‌ها',
                        'description' => 'تعریف نقش و تخصیص مجوز به نقش',
                        'email' => 'identity.roles@demo.local',
                        'mobile' => '09121000003',
                        'first_name' => 'کارشناس',
                        'last_name' => 'نقش',
                        'permissions' => array_merge($identityBase, [
                            'identity.role.view', 'identity.role.create', 'identity.role.update',
                            'identity.role.assign', 'identity.role.assign-permissions', 'identity.permission.view',
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
                    'accounting.voucher.view', 'accounting.voucher.post', 'accounting.account.view',
                    'procurement.payment-schedule.view', 'procurement.cash-transaction.view', 'procurement.cash-transaction.create',
                    'procurement.sales-invoice.view', 'procurement.purchase-invoice.view',
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
                            'accounting.voucher.view', 'accounting.voucher.post', 'accounting.account.view',
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
                            'accounting.voucher.view', 'accounting.account.view',
                        ]),
                    ],
                ],
            ],
            [
                'code' => 'sales-manager',
                'name' => 'مدیر فروش',
                'description' => 'مدیریت سفارش و فاکتور فروش',
                'email' => 'sales.manager@demo.local',
                'mobile' => '09121000021',
                'first_name' => 'مدیر',
                'last_name' => 'فروش',
                'permissions' => array_merge($identityBase, [
                    'procurement.sales-order.view', 'procurement.sales-order.create', 'procurement.sales-order.confirm',
                    'procurement.sales-quotation.create',
                    'procurement.sales-delivery.view', 'procurement.sales-delivery.create', 'procurement.sales-delivery.post',
                    'procurement.sales-invoice.view', 'procurement.sales-invoice.create', 'procurement.sales-invoice.update', 'procurement.sales-invoice.post',
                    'procurement.return-order.create',
                    'masterdata.business-partner.view', 'masterdata.business-partner.create', 'masterdata.business-partner.update',
                ]),
                'children' => [
                    [
                        'code' => 'sales-rep',
                        'name' => 'کارشناس فروش',
                        'description' => 'ثبت سفارش بدون قطعی‌سازی فاکتور',
                        'email' => 'sales.rep@demo.local',
                        'mobile' => '09121000022',
                        'first_name' => 'کارشناس',
                        'last_name' => 'فروش',
                        'permissions' => array_merge($identityBase, [
                            'procurement.sales-order.view', 'procurement.sales-order.create',
                            'procurement.sales-quotation.create', 'procurement.sales-invoice.view',
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
                    'procurement.purchase-requisition.view', 'procurement.purchase-requisition.create',
                    'procurement.purchase-requisition.submit', 'procurement.purchase-requisition.approve',
                    'procurement.purchase-order.create',
                    'procurement.purchase-receipt.view', 'procurement.purchase-receipt.create', 'procurement.purchase-receipt.post',
                    'procurement.purchase-invoice.view', 'procurement.purchase-invoice.create', 'procurement.purchase-invoice.post',
                    'masterdata.business-partner.view', 'masterdata.business-partner.create',
                ]),
                'children' => [
                    [
                        'code' => 'purchase-clerk',
                        'name' => 'کارشناس خرید',
                        'description' => 'ثبت درخواست و سفارش',
                        'email' => 'purchase.clerk@demo.local',
                        'mobile' => '09121000032',
                        'first_name' => 'کارشناس',
                        'last_name' => 'خرید',
                        'permissions' => array_merge($identityBase, [
                            'procurement.purchase-requisition.view', 'procurement.purchase-requisition.create',
                            'procurement.purchase-requisition.submit', 'procurement.purchase-order.create',
                            'procurement.purchase-receipt.view', 'masterdata.business-partner.view',
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
                    'inventory.item.view', 'inventory.item.create', 'inventory.warehouse.view',
                    'inventory.document.view', 'inventory.document.create', 'inventory.document.post',
                ]),
                'children' => [
                    [
                        'code' => 'warehouse-clerk',
                        'name' => 'انباردار',
                        'description' => 'ثبت اسناد موجودی',
                        'email' => 'warehouse.clerk@demo.local',
                        'mobile' => '09121000042',
                        'first_name' => 'انباردار',
                        'last_name' => 'عملیاتی',
                        'permissions' => array_merge($identityBase, [
                            'inventory.item.view', 'inventory.warehouse.view',
                            'inventory.document.view', 'inventory.document.create',
                        ]),
                    ],
                ],
            ],
            [
                'code' => 'operations-viewer',
                'name' => 'ناظر عملیات',
                'description' => 'دسترسی فقط‌خواندنی چندماژوله',
                'email' => 'ops.viewer@demo.local',
                'mobile' => '09121000051',
                'first_name' => 'ناظر',
                'last_name' => 'عملیات',
                'permissions' => array_merge($identityBase, [
                    'identity.role.view', 'identity.permission.view', 'identity.membership_history.view',
                    'accounting.voucher.view', 'accounting.account.view',
                    'inventory.item.view', 'inventory.warehouse.view', 'inventory.document.view',
                    'procurement.sales-order.view', 'procurement.sales-invoice.view',
                    'procurement.purchase-receipt.view', 'procurement.purchase-invoice.view',
                    'masterdata.business-partner.view',
                    'workflow.instance.view', 'workflow.task.view',
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
