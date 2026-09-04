<?php

namespace Tests\Unit\Modules\Inventory;

use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\WarehouseLookupService;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WarehouseLookupServiceTest extends TestCase
{
    private WarehouseLookupService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WarehouseLookupService::class);
        Context::add('tenant_id', (string) Str::uuid());
        Context::add('user_id', (string) Str::uuid());
    }

    #[Test]
    public function find_by_id_returns_active_warehouse(): void
    {
        $warehouse = Warehouse::create([
            'tenant_id' => Context::get('tenant_id'),
            'branch_id' => (string) Str::uuid(),
            'code' => 'WH-001',
            'name' => 'Main Warehouse',
            'is_bonded' => false,
            'status' => 1,
            'created_by' => Context::get('user_id'),
        ]);
        $found = $this->service->findById($warehouse->warehouse_id);
        $this->assertNotNull($found);
        $this->assertSame('WH-001', $found->code);
    }

    #[Test]
    public function exists_returns_false_for_inactive_warehouse(): void
    {
        $warehouse = Warehouse::create([
            'tenant_id' => Context::get('tenant_id'),
            'branch_id' => (string) Str::uuid(),
            'code' => 'WH-002',
            'name' => 'Inactive',
            'is_bonded' => false,
            'status' => 2,
            'created_by' => Context::get('user_id'),
        ]);
        $this->assertFalse($this->service->exists($warehouse->warehouse_id));
    }

    #[Test]
    public function get_basic_info_returns_expected_array(): void
    {
        $warehouse = Warehouse::create([
            'tenant_id' => Context::get('tenant_id'),
            'branch_id' => (string) Str::uuid(),
            'code' => 'WH-003',
            'name' => 'Info Warehouse',
            'is_bonded' => true,
            'status' => 1,
            'created_by' => Context::get('user_id'),
        ]);
        $info = $this->service->getBasicInfo($warehouse->warehouse_id);
        $this->assertIsArray($info);
        $this->assertArrayHasKey('branch_id', $info);
        $this->assertTrue($info['is_bonded']);
    }
}
