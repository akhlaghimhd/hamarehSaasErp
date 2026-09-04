<?php

namespace Tests\Unit\Modules\Inventory;

use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Services\ItemLookupService;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ItemLookupServiceTest extends TestCase
{
    private ItemLookupService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ItemLookupService::class);
        Context::add('tenant_id', (string) Str::uuid());
        Context::add('user_id', (string) Str::uuid());
    }

    #[Test]
    public function find_by_id_returns_active_item(): void
    {
        $item = Item::create([
            'tenant_id' => Context::get('tenant_id'),
            'item_group_id' => (string) Str::uuid(),
            'uom_id' => (string) Str::uuid(),
            'code' => 'ITM-001',
            'name' => 'Test Item',
            'item_type' => 1,
            'valuation_method' => 1,
            'status' => 1,
            'created_by' => Context::get('user_id'),
        ]);
        $found = $this->service->findById($item->item_id);
        $this->assertNotNull($found);
        $this->assertSame($item->item_id, $found->item_id);
    }

    #[Test]
    public function find_by_id_returns_null_for_inactive_item(): void
    {
        $item = Item::create([
            'tenant_id' => Context::get('tenant_id'),
            'item_group_id' => (string) Str::uuid(),
            'uom_id' => (string) Str::uuid(),
            'code' => 'ITM-002',
            'name' => 'Inactive',
            'item_type' => 1,
            'valuation_method' => 1,
            'status' => 2,
            'created_by' => Context::get('user_id'),
        ]);
        $this->assertNull($this->service->findById($item->item_id));
    }

    #[Test]
    public function exists_returns_true_for_active_item(): void
    {
        $item = Item::create([
            'tenant_id' => Context::get('tenant_id'),
            'item_group_id' => (string) Str::uuid(),
            'uom_id' => (string) Str::uuid(),
            'code' => 'ITM-003',
            'name' => 'Exists',
            'item_type' => 2,
            'valuation_method' => 1,
            'status' => 1,
            'created_by' => Context::get('user_id'),
        ]);
        $this->assertTrue($this->service->exists($item->item_id));
    }

    #[Test]
    public function get_basic_info_returns_expected_array(): void
    {
        $item = Item::create([
            'tenant_id' => Context::get('tenant_id'),
            'item_group_id' => (string) Str::uuid(),
            'uom_id' => (string) Str::uuid(),
            'code' => 'ITM-004',
            'name' => 'Basic Info Item',
            'item_type' => 1,
            'valuation_method' => 1,
            'status' => 1,
            'created_by' => Context::get('user_id'),
        ]);
        $info = $this->service->getBasicInfo($item->item_id);
        $this->assertIsArray($info);
        $this->assertSame('ITM-004', $info['code']);
        $this->assertArrayHasKey('uom_id', $info);
    }
}
