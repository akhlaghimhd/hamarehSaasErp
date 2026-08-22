<?php

namespace App\Modules\MasterData\Services;

use App\Modules\MasterData\Models\CostCenter;
use App\Modules\MasterData\DTOs\CreateCostCenterDTO;
use App\Modules\MasterData\DTOs\UpdateCostCenterDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Exception;
use Illuminate\Database\Eloquent\Collection;

class CostCenterService
{
    public function getAllCostCenters(): Collection
    {
        return CostCenter::all();
    }

    public function getCostCenterById(string $id): CostCenter
    {
        return CostCenter::findOrFail($id);
    }

    public function createCostCenter(CreateCostCenterDTO $dto): CostCenter
    {
        try {
            return DB::transaction(function () use ($dto) {
                $tenantId = Context::get('tenant_id');

                $costCenter = CostCenter::create([
                    'tenant_id'             => $tenantId,
                    'company_id'            => $dto->companyId,
                    'department_id'         => $dto->departmentId,
                    'parent_cost_center_id' => $dto->parentCostCenterId,
                    'code'                  => $dto->code,
                    'name'                  => $dto->name,
                    'type'                  => $dto->type,
                    'status'                => $dto->status,
                    'created_by'            => Context::get('user_id'),
                ]);

                // ⚡ ثبت رویداد ساخت مرکز هزینه در Outbox
                $this->dispatchOutboxEvent('master_data.cost_center.created', $costCenter, $tenantId);

                Log::info("CostCenter created successfully.", ['id' => $costCenter->cost_center_id ?? $costCenter->id, 'tenant_id' => $tenantId]);

                return $costCenter;
            });
        } catch (Exception $e) {
            Log::error("Failed to create Cost Center: " . $e->getMessage());
            throw $e;
        }
    }

    public function updateCostCenter(string $id, UpdateCostCenterDTO $dto): CostCenter
    {
        try {
            return DB::transaction(function () use ($id, $dto) {
                $costCenter = CostCenter::findOrFail($id);
                $tenantId = Context::get('tenant_id');

                $updateData = array_filter([
                    'name'                  => $dto->name,
                    'type'                  => $dto->type,
                    'status'                => $dto->status,
                    'company_id'            => $dto->company_id,
                    'department_id'         => $dto->department_id,
                    'parent_cost_center_id' => $dto->parent_cost_center_id,
                    'updated_by'            => Context::get('user_id'),
                ], fn($value) => !is_null($value));

                $costCenter->update($updateData);

                // ⚡ ثبت رویداد آپدیت در Outbox
                $this->dispatchOutboxEvent('master_data.cost_center.updated', $costCenter, $tenantId);

                return $costCenter;
            });
        } catch (Exception $e) {
            Log::error("Failed to update Cost Center: " . $e->getMessage());
            throw $e;
        }
    }

    public function deleteCostCenter(string $id): void
    {
        try {
            DB::transaction(function () use ($id) {
                $costCenter = CostCenter::findOrFail($id);
                $tenantId = Context::get('tenant_id');

                $costCenter->update(['deleted_by' => Context::get('user_id')]);
                $costCenter->delete();

                // ⚡ ثبت رویداد حذف در Outbox
                $this->dispatchOutboxEvent('master_data.cost_center.deleted', $costCenter, $tenantId);
            });
        } catch (Exception $e) {
            Log::error("Failed to delete Cost Center: " . $e->getMessage());
            throw $e;
        }
    }

    private function dispatchOutboxEvent(string $eventType, CostCenter $costCenter, string $tenantId): void
    {
        DB::table('event_outbox')->insert([
            'event_id'       => Str::uuid()->toString(),
            'tenant_id'      => $tenantId,
            'aggregate_type' => 'cost_centers',
            'aggregate_id'   => $costCenter->cost_center_id ?? $costCenter->id,
            'event_type'     => $eventType,
            'payload'        => json_encode($costCenter->toArray()),
            'status'         => 1,
            'created_at'     => now(),
        ]);
    }
}