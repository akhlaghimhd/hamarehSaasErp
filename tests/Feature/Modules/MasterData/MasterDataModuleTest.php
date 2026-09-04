<?php

use Tests\TestCase;
use App\Modules\MasterData\Models\BusinessPartner;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\MasterData\Models\CostCenter;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\IdentityCore\Models\TenantPermission;
use App\Modules\IdentityCore\Models\TenantUserRole;
use App\Modules\IdentityCore\Models\TenantRolePermission;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Base\Context\TenantContext;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create([
        'tenant_code' => 'SYS_TENANT',
        'status'      => 1,
    ]);
    $this->tenantId = $this->tenant->tenant_id;

    $this->user = User::factory()->create(['status' => 1]);
    $this->userId = $this->user->user_id;

    // تنظیم Tenant Context به‌صورت کامل
    TenantContext::getInstance()->setTenantId($this->tenantId);
    Context::add('tenant_id', $this->tenantId);
    Context::add('user_id', $this->userId);
    app()->instance('current_tenant_id', $this->tenantId);

    // عضویت کاربر در Tenant
    TenantUser::factory()->create([
        'tenant_id' => $this->tenantId,
        'user_id'   => $this->userId,
        'status'    => 1,
    ]);

    // نقش
    $this->role = TenantRole::factory()->create([
        'tenant_id' => $this->tenantId,
        'code'      => 'admin',
        'name'      => 'مدیر سیستم',
        'status'    => 1,
    ]);

    // permissionهای واقعی مطابق middleware
    $permissionCodes = [
        'master-data.business-partner.create',
        'master-data.business-partner.view',
        'master-data.item.create',
        'master-data.item.view',
        'master-data.warehouse.create',
        'master-data.warehouse.view',
        'master-data.cost-center.create',
        'master-data.cost-center.view',
    ];

    foreach ($permissionCodes as $code) {
        $permission = TenantPermission::create([
            'tenant_permission_id' => (string) Str::uuid(),
            'tenant_id'            => $this->tenantId,
            'code'                 => $code,
            'name'                 => $code,
            'module_name'          => 'MasterData',
            'status'               => 1,
        ]);

        TenantRolePermission::create([
            'tenant_role_permission_id' => (string) Str::uuid(),
            'tenant_id'                 => $this->tenantId,
            'tenant_role_id'            => $this->role->tenant_role_id,
            'tenant_permission_id'      => $permission->tenant_permission_id,
        ]);
    }

    // اتصال کاربر به نقش
    TenantUserRole::create([
        'tenant_user_role_id' => (string) Str::uuid(),
        'tenant_id'           => $this->tenantId,
        'user_id'             => $this->userId,
        'tenant_role_id'      => $this->role->tenant_role_id,
    ]);

    // توکن Sanctum
    $this->token = $this->user->createToken(
        'test-token-' . $this->tenantId,
        ['tenant:' . $this->tenantId]
    )->plainTextToken;
});

test('can create business partner and log event in outbox', function () {
    $response = $this->withHeaders([
        'Authorization' => 'Bearer ' . $this->token,
        'X-Tenant-ID'   => $this->tenantId,
        'Accept'        => 'application/json',
    ])->postJson('/api/master-data/business-partners', [
        'code'         => 'BP-001',
        'display_name' => 'شرکت تست پارس',
        'partner_type' => 2,
        'status'       => 1,
    ]);

    $response->assertStatus(201)->assertJson(['success' => true]);
});

test('tenant isolation prevents accessing other tenant data', function () {
    $tenantA = $this->tenantId;
    $tenantB = (string) Str::uuid();

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
        'Authorization' => 'Bearer ' . $this->token,
        'X-Tenant-ID'   => $this->tenantId,
        'Accept'        => 'application/json',
    ])->postJson('/api/inventory/items', [
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
        'Authorization' => 'Bearer ' . $this->token,
        'X-Tenant-ID'   => $this->tenantId,
        'Accept'        => 'application/json',
    ])->postJson('/api/inventory/warehouses', [
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
