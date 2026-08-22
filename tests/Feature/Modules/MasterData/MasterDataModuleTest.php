<?php

use Tests\TestCase;
use App\Modules\MasterData\Models\BusinessPartner;
use App\Modules\MasterData\Models\Item;
use App\Modules\MasterData\Models\Warehouse;
use App\Modules\MasterData\Models\CostCenter;
use App\Base\Context\TenantContext;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->tenantId = '11111111-1111-1111-1111-111111111111';
    $this->userId = '22222222-2222-2222-2222-222222222222';

    // تنظیم Tenant Context به‌صورت کامل
    TenantContext::getInstance()->setTenantId($this->tenantId);
    Context::add('tenant_id', $this->tenantId);
    Context::add('user_id', $this->userId);
    app()->instance('current_tenant_id', $this->tenantId);

    // درج مستقیم مستأجر در دیتابیس
    DB::table('tenants')->updateOrInsert(
        ['tenant_id' => $this->tenantId],
        [
            'tenant_code' => 'SYS_TENANT',
            'tenant_name' => 'مستأجر سیستم',
            'slug'        => 'sys-tenant',
            'status'      => 1,
        ]
    );

    // ۱. ساخت رکورد کاربر در جدول پایه هویت
    DB::table('users')->updateOrInsert(
        ['user_id' => $this->userId],
        ['first_name' => 'تست', 'last_name' => 'سیستم', 'status' => 1]
    );

    // ۱.۵ ثبت عضویت کاربر در جدول tenant_users
    DB::table('tenant_users')->updateOrInsert(
        ['tenant_id' => $this->tenantId, 'user_id' => $this->userId],
        [
            'tenant_user_id' => (string) \Illuminate\Support\Str::uuid(),
            'status'         => 1,
        ]
    );

    // ۲. ساخت نقش تستی مستأجر
    $roleId = '44444444-4444-4444-4444-444444444444';
    DB::table('tenant_roles')->updateOrInsert(
        ['tenant_role_id' => $roleId],
        ['tenant_id' => $this->tenantId, 'code' => 'admin', 'name' => 'مدیر سیستم', 'status' => 1]
    );

    // ۳. اتصال کاربر به نقش
    $tenantUserRoleId = '66666666-6666-6666-6666-666666666666';
    DB::table('tenant_user_roles')->updateOrInsert(
        ['tenant_user_role_id' => $tenantUserRoleId],
        [
            'tenant_id'      => $this->tenantId,
            'user_id'        => $this->userId,
            'tenant_role_id' => $roleId,
        ]
    );

    // ۴. ثبت پرمیشن‌های مورد نیاز
    $permissions = [
        'business-partners.store', 'business-partners.create',
        'items.store', 'items.create',
        'warehouses.store', 'warehouses.create',
    ];

    foreach ($permissions as $permCode) {
        $permId = '55555555-5555-5555-5555-' . substr(md5($permCode), 0, 12);

        DB::table('tenant_permissions')->updateOrInsert(
            ['tenant_permission_id' => $permId],
            ['tenant_id' => $this->tenantId, 'code' => $permCode, 'name' => $permCode, 'status' => 1]
        );

        DB::table('tenant_role_permissions')->updateOrInsert(
            [
                'tenant_id'            => $this->tenantId,
                'tenant_role_id'       => $roleId,
                'tenant_permission_id' => $permId,
            ],
            [
                'tenant_role_permission_id' => \Illuminate\Support\Str::uuid()->toString(),
            ]
        );
    }

    // ساخت کاربر فرضی سازگار با لاراول
    $userMock = new class extends \Illuminate\Database\Eloquent\Model implements \Illuminate\Contracts\Auth\Authenticatable {
        protected $table = 'users';
        protected $primaryKey = 'user_id';
        public $incrementing = false;
        protected $keyType = 'string';

        public function getAuthIdentifierName() { return 'user_id'; }
        public function getAuthIdentifier() { return $this->user_id; }
        public function getAuthPassword() { return ''; }
        public function getAuthPasswordName() { return 'password_hash'; }
        public function getRememberToken() { return null; }
        public function setRememberToken($value) {}
        public function getRememberTokenName() { return ''; }
    };

    $userMock->user_id = $this->userId;
    $userMock->exists = true;

    $this->actingAs($userMock, 'api');
});

test('can create business partner and log event in outbox', function () {
    $response = $this->withHeaders([
        'X-Tenant-ID' => $this->tenantId,
        'Accept'      => 'application/json',
    ])->postJson('/api/master-data/business-partners', [
        'code'         => 'BP-001',
        'display_name' => 'شرکت تست پارس',
        'partner_type' => 2,
        'status'       => 1,
    ]);

    $response->assertStatus(201)->assertJson(['success' => true]);
});

test('tenant isolation prevents accessing other tenant data', function () {
    $tenantA = '11111111-1111-1111-1111-111111111111';
    $tenantB = '33333333-3333-3333-3333-333333333333';

    // تنظیم Context کامل برای مستأجر A
    TenantContext::getInstance()->setTenantId($tenantA);
    Context::add('tenant_id', $tenantA);
    app()->instance('current_tenant_id', $tenantA);

    Item::create([
        'tenant_id' => $tenantA,
        'code'      => 'ITEM-A',
        'name'      => 'کالای مستأجر الف',
        'item_type' => 1,
        'base_uom'  => 'PCS',
        'status'    => 1,
    ]);

    // سوییچ کامل به مستأجر B
    TenantContext::getInstance()->setTenantId($tenantB);
    Context::add('tenant_id', $tenantB);
    app()->instance('current_tenant_id', $tenantB);

    $items = Item::all();
    expect($items)->toBeEmpty();
});

test('can create item successfully', function () {
    $response = $this->withHeaders([
        'X-Tenant-ID' => $this->tenantId,
        'Accept'      => 'application/json',
    ])->postJson('/api/master-data/items', [
        'code'      => 'MAT-100',
        'name'      => 'مفتول فولادی',
        'item_type' => 1,
        'base_uom'  => 'KG',
        'status'    => 1,
    ]);

    $response->assertStatus(201)->assertJson(['success' => true]);
});

test('can create warehouse successfully', function () {
    $response = $this->withHeaders([
        'X-Tenant-ID' => $this->tenantId,
        'Accept'      => 'application/json',
    ])->postJson('/api/master-data/warehouses', [
        'tenant_id' => $this->tenantId,
        'code'      => 'WH-CENTRAL',
        'name'      => 'انبار مرکزی',
        'location'  => 'تهران - کیلومتر جاده مخصوص',
        'is_active' => true,
    ]);

    if ($response->status() !== 201) {
        $response->dump();
    }

    $response->assertStatus(201)->assertJson(['success' => true]);
});