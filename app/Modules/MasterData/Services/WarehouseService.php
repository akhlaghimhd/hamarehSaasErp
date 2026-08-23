<?php

namespace App\Modules\MasterData\Services;

use App\Modules\MasterData\Models\Warehouse;
use App\Modules\MasterData\DTOs\CreateWarehouseDTO;
use App\Modules\MasterData\DTOs\UpdateWarehouseDTO;
use App\Base\Context\ScopeContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Exception;
use Illuminate\Database\Eloquent\Collection;

class WarehouseService
{
    public function getAllWarehouses(): Collection
    {
        $query = Warehouse::query();

        // اعمال فیلتر Scope در صورت وجود Scope از نوع WAREHOUSE
        $warehouseReferenceIds = ScopeContext::getInstance()->getReferenceIdsByType('WAREHOUSE');

        if (!empty($warehouseReferenceIds)) {
            $query->whereIn('warehouse_id', $warehouseReferenceIds);
        }

        return $query->get();
    }

    public function getWarehouseById(string $id): Warehouse
    {
        $warehouse = Warehouse::findOrFail($id);

        $this->ensureScopeAccess('WAREHOUSE', $id);

        return $warehouse;
    }

    public function createWarehouse(CreateWarehouseDTO $dto): Warehouse
    {
        try {
            return DB::transaction(function () use ($dto) {
                $tenantId = Context::get('tenant_id');

                $warehouse = Warehouse::create([
                    'tenant_id'  => $tenantId,
                    'code'       => $dto->code,
                    'name'       => $dto->name,
                    'location'   => $dto->location,
                    'is_active'  => $dto->isActive,
                    'created_by' => Context::get('user_id'),
                ]);

                // ⚡ ثبت رویداد ایجاد انبار در Outbox
                $this->dispatchOutboxEvent('master_data.warehouse.created', $warehouse, $tenantId);

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

                // بررسی دسترسی Scope
                $this->ensureScopeAccess('WAREHOUSE', $id);

                $updateData = array_filter([
                    'name'       => $dto->name,
                    'location'   => $dto->location,
                    'is_active'  => $dto->isActive,
                    'updated_by' => Context::get('user_id'),
                ], fn($value) => !is_null($value));

                $warehouse->update($updateData);

                // ⚡ ثبت رویداد ویرایش انبار در Outbox
                $this->dispatchOutboxEvent('master_data.warehouse.updated', $warehouse, $tenantId);

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

                // بررسی دسترسی Scope
                $this->ensureScopeAccess('WAREHOUSE', $id);

                $warehouse->update(['deleted_by' => Context::get('user_id')]);
                $warehouse->delete();

                // ⚡ ثبت رویداد حذف انبار در Outbox
                $this->dispatchOutboxEvent('master_data.warehouse.deleted', $warehouse, $tenantId);
            });
        } catch (Exception $e) {
            Log::error("Failed to delete Warehouse: " . $e->getMessage());
            throw $e;
        }
    }

    private function ensureScopeAccess(string $scopeType, string $referenceId): void
    {
        $scopeContext = ScopeContext::getInstance();
        $referenceIds = $scopeContext->getReferenceIdsByType($scopeType);

        if (!empty($referenceIds) && !in_array($referenceId, $referenceIds, true)) {
            throw new Exception("شما دسترسی لازم به این منبع را ندارید.");
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