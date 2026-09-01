<?php

namespace App\Modules\MasterData\Services;

use App\Modules\MasterData\Models\UnitOfMeasure;
use App\Modules\MasterData\DTOs\CreateUnitOfMeasureDTO;
use App\Modules\MasterData\DTOs\UpdateUnitOfMeasureDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Context;
use Exception;
use Illuminate\Database\Eloquent\Collection;

/**
 * UnitOfMeasure is Tenant-Owned Master Data (L5-MD-P03).
 * Schema has tenant_id + unique(tenant_id, code). Model uses TenantScoped.
 */
class UnitOfMeasureService
{
    public function getAll(): Collection
    {
        return UnitOfMeasure::all();
    }

    public function getById(string $id): UnitOfMeasure
    {
        return UnitOfMeasure::findOrFail($id);
    }

    public function create(CreateUnitOfMeasureDTO $dto): UnitOfMeasure
    {
        try {
            return DB::transaction(function () use ($dto) {
                $tenantId = Context::get('tenant_id');

                return UnitOfMeasure::create([
                    'tenant_id'         => $tenantId,
                    'code'              => $dto->code,
                    'name'              => $dto->name,
                    'decimal_places'    => $dto->decimal_places,
                    'conversion_factor' => $dto->conversion_factor,
                    'status'            => $dto->status,
                    'created_by'        => Context::get('user_id'),
                    'row_version'       => 1,
                ]);
            });
        } catch (Exception $e) {
            Log::error('Failed to create UnitOfMeasure: ' . $e->getMessage());
            throw $e;
        }
    }

    public function update(string $id, UpdateUnitOfMeasureDTO $dto): UnitOfMeasure
    {
        try {
            return DB::transaction(function () use ($id, $dto) {
                $unit = UnitOfMeasure::findOrFail($id);

                $updateData = array_filter([
                    'code'              => $dto->code,
                    'name'              => $dto->name,
                    'decimal_places'    => $dto->decimal_places,
                    'conversion_factor' => $dto->conversion_factor,
                    'status'            => $dto->status,
                    'updated_by'        => Context::get('user_id'),
                ], fn($value) => $value !== null);

                $updateData['row_version'] = ((int) ($unit->row_version ?? 1)) + 1;

                $unit->update($updateData);

                return $unit->fresh();
            });
        } catch (Exception $e) {
            Log::error('Failed to update UnitOfMeasure: ' . $e->getMessage());
            throw $e;
        }
    }

    public function delete(string $id): void
    {
        try {
            DB::transaction(function () use ($id) {
                $unit = UnitOfMeasure::findOrFail($id);
                $unit->update(['deleted_by' => Context::get('user_id')]);
                $unit->delete();
            });
        } catch (Exception $e) {
            Log::error('Failed to delete UnitOfMeasure: ' . $e->getMessage());
            throw $e;
        }
    }
}
