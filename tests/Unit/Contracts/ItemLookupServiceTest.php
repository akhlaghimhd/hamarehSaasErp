<?php

namespace Tests\Unit\Contracts;

use App\Modules\MasterData\Contracts\ItemLookupContract;
use App\Modules\MasterData\Models\Item;
use App\Modules\MasterData\Services\ItemLookupService;
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

        $this->service = app(ItemLookupContract::class);

        $tenantId = (string) Str::uuid();
        Context::add('tenant_id', $tenantId);
        Context::add('user_id', (string) Str::uuid());
    }

    #[Test]
    public function it_is_bound_to_item_lookup_contract(): void
    {
        $this->assertInstanceOf(ItemLookupService::class, $this->service);
    }

    #[Test]
    public function find_by_id_returns_active_item(): void
    {
        $item = Item::create([
            'tenant_id'  => Context::get('tenant_id'),
            'code'       => 'ITM-001',
            'name'       => 'Test Item',
            'item_type'  => 1,
            'base_uom'   => 'PCS',
            'status'     => 1,
            'created_by' => Context::get('user_id'),
        ]);

        $found = $this->service->findById($item->item_id);

        $this->assertNotNull($found);
        $this->assertSame($item->item_id, $found->item_id);
        $this->assertSame('ITM-001', $found->code);
    }

    #[Test]
    public function find_by_id_returns_null_for_inactive_item(): void
    {
        $item = Item::create([
            'tenant_id'  => Context::get('tenant_id'),
            'code'       => 'ITM-002',
            'name'       => 'Inactive Item',
            'item_type'  => 1,
            'base_uom'   => 'PCS',
            'status'     => 2,
            'created_by' => Context::get('user_id'),
        ]);

        $found = $this->service->findById($item->item_id);

        $this->assertNull($found);
    }

    #[Test]
    public function exists_returns_true_for_active_item(): void
    {
        $item = Item::create([
            'tenant_id'  => Context::get('tenant_id'),
            'code'       => 'ITM-003',
            'name'       => 'Exists Item',
            'item_type'  => 2,
            'base_uom'   => 'KG',
            'status'     => 1,
            'created_by' => Context::get('user_id'),
        ]);

        $this->assertTrue($this->service->exists($item->item_id));
    }

    #[Test]
    public function get_basic_info_returns_expected_array(): void
    {
        $item = Item::create([
            'tenant_id'  => Context::get('tenant_id'),
            'code'       => 'ITM-004',
            'name'       => 'Basic Info Item',
            'item_type'  => 1,
            'base_uom'   => 'PCS',
            'status'     => 1,
            'created_by' => Context::get('user_id'),
        ]);

        $info = $this->service->getBasicInfo($item->item_id);

        $this->assertIsArray($info);
        $this->assertSame($item->item_id, $info['item_id']);
        $this->assertSame('ITM-004', $info['code']);
        $this->assertSame('Basic Info Item', $info['name']);
        $this->assertSame(1, $info['item_type']);
        $this->assertSame('PCS', $info['base_uom']);
    }
}
