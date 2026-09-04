<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\DTOs\CreateWarehouseDTO;
use App\Modules\Inventory\DTOs\UpdateWarehouseDTO;
use App\Base\Context\ScopeContext;
use App\Base\Services\ScopeAccessGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Exception;
use Illuminate\Database\Eloquent\Collection;

class WarehouseService
{
    public function __construct(
        protected ScopeAccessGuard $scopeAccessGuard = new ScopeAccessGuard()
    ) {
    }

    public function getAllWarehouses(): Collection
    {
        $query = Warehouse::query();

        $warehouseReferenceIds = ScopeContext::getInstance()->getReferenceIdsByType('WAREHOUSE');

        if (!empty($warehouseReferenceIds)) {
            $query->whereIn('warehouse_id', $warehouseReferenceIds);
        }

        return $query->get();
    }

    public function getWarehouseById(string $id): Warehouse
    {
        $warehouse = Warehouse::findOrFail($id);

        $this->scopeAccessGuard->assertAccess('WAREHOUSE', $id);

        return $warehouse;
    }

    public function createWarehouse(CreateWarehouseDTO $dto): Warehouse
    {
        try {
            return DB::transaction(function () use ($dto) {
                $tenantId = Context::get('tenant_id');

                $warehouse = Warehouse::create([
                    'tenant_id'   => $tenantId,
                    'code'        => $dto->code,
                    'name'        => $dto->name,
                    'location'    => $dto->location,
                    'is_active'   => $dto->isActive,
                    'created_by'  => Context::get('user_id'),
                    'row_version' => 1,
                ]);

                $this->dispatchOutboxEvent('inventory.warehouse.created', $warehouse, $tenantId);

                Log::info("Warehouse created successfully.", ['id' => $warehouse->warehouse_id, 'tenant_id' => $tenantId]);

                return $warehouse;
            });
        } catch (Exception $e) {
            Log::error("Failed to create Warehouse: " . $e->getMessage());
            throw $e;
        }
    }

    public function updateWarehouse(string $id, UpdateWarehouseDTO $dto): Warehouse
    {
        try {
            return DB::transaction(function () use ($id, $dto) {
                $warehouse = Warehouse::findOrFail($id);
                $tenantId = Context::get('tenant_id');

                $this->scopeAccessGuard->assertAccess('WAREHOUSE', $id);

                $updateData = array_filter([
                    'name'       => $dto->name,
                    'location'   => $dto->location,
                    'is_active'  => $dto->isActive,
                    'updated_by' => Context::get('user_id'),
                ], fn($value) => !is_null($value));

                $updateData['row_version'] = ((int) ($warehouse->row_version ?? 1)) + 1;

                $warehouse->update($updateData);

                $this->dispatchOutboxEvent('inventory.warehouse.updated', $warehouse, $tenantId);

                return $warehouse;
            });
        } catch (Exception $e) {
            Log::error("Failed to update Warehouse: " . $e->getMessage());
            throw $e;
        }
    }

    public function deleteWarehouse(string $id): void
    {
        try {
            DB::transaction(function () use ($id) {
                $warehouse = Warehouse::findOrFail($id);
                $tenantId = Context::get('tenant_id');

                $this->scopeAccessGuard->assertAccess('WAREHOUSE', $id);

                $warehouse->update(['deleted_by' => Context::get('user_id')]);
                $warehouse->delete();

                $this->dispatchOutboxEvent('inventory.warehouse.deleted', $warehouse, $tenantId);
            });
        } catch (Exception $e) {
            Log::error("Failed to delete Warehouse: " . $e->getMessage());
            throw $e;
        }
    }

    private function dispatchOutboxEvent(string $eventType, Warehouse $warehouse, string $tenantId): void
    {
        DB::table('event_outbox')->insert([
            'event_id'       => Str::uuid()->toString(),
            'tenant_id'      => $tenantId,
            'aggregate_type' => 'warehouses',
            'aggregate_id'   => $warehouse->warehouse_id,
            'event_type'     => $eventType,
            'payload'        => json_encode($warehouse->toArray()),
            'status'         => 1,
            'created_at'     => now(),
        ]);
    }
}
