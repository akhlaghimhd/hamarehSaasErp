<?php

namespace Tests\Unit\Modules\MasterData;

use App\Modules\MasterData\Models\Warehouse;
use App\Modules\MasterData\Services\WarehouseLookupService;
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

        $tenantId = (string) Str::uuid();
        Context::add('tenant_id', $tenantId);
        Context::add('user_id', (string) Str::uuid());
    }

    #[Test]
    public function find_by_id_returns_active_warehouse(): void
    {
        $warehouse = Warehouse::create([
            'tenant_id'  => Context::get('tenant_id'),
            'code'       => 'WH-001',
            'name'       => 'Main Warehouse',
            'location'   => 'Tehran',
            'is_active'  => true,
            'created_by' => Context::get('user_id'),
        ]);

        $found = $this->service->findById($warehouse->warehouse_id);

        $this->assertNotNull($found);
        $this->assertSame($warehouse->warehouse_id, $found->warehouse_id);
        $this->assertSame('WH-001', $found->code);
    }

    #[Test]
    public function exists_returns_false_for_inactive_warehouse(): void
    {
        $warehouse = Warehouse::create([
            'tenant_id'  => Context::get('tenant_id'),
            'code'       => 'WH-002',
            'name'       => 'Closed Warehouse',
            'is_active'  => false,
            'created_by' => Context::get('user_id'),
        ]);

        $this->assertFalse($this->service->exists($warehouse->warehouse_id));
    }

    #[Test]
    public function get_basic_info_returns_expected_keys(): void
    {
        $warehouse = Warehouse::create([
            'tenant_id'  => Context::get('tenant_id'),
            'code'       => 'WH-003',
            'name'       => 'Secondary',
            'location'   => 'Isfahan',
            'is_active'  => true,
            'created_by' => Context::get('user_id'),
        ]);

        $info = $this->service->getBasicInfo($warehouse->warehouse_id);

        $this->assertIsArray($info);
        $this->assertArrayHasKey('warehouse_id', $info);
        $this->assertArrayHasKey('code', $info);
        $this->assertArrayHasKey('name', $info);
        $this->assertArrayHasKey('is_active', $info);
    }
}
