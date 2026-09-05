<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\DTOs\CreateLocationDTO;
use App\Modules\Inventory\DTOs\UpdateLocationDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class LocationService
{
    public function __construct(
        private readonly WarehouseLookupService $warehouseLookup,
    ) {
    }

    public function getAllLocations(?string $warehouseId = null): Collection
    {
        $query = Location::query();

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        return $query->orderBy('code')->get();
    }

    public function getLocationById(string $id): Location
    {
        return Location::findOrFail($id);
    }

    public function createLocation(CreateLocationDTO $dto): Location
    {
        try {
            return DB::transaction(function () use ($dto) {
                $tenantId = Context::get('tenant_id');

                $this->warehouseLookup->requireActive($dto->warehouse_id);

                if ($dto->parent_location_id) {
                    $parent = Location::findOrFail($dto->parent_location_id);
                    if ($parent->warehouse_id !== $dto->warehouse_id) {
                        throw ValidationException::withMessages([
                            'parent_location_id' => ['Parent location must belong to the same warehouse.'],
                        ]);
                    }
                }

                $location = Location::create([
                    'tenant_id'          => $tenantId,
                    'warehouse_id'       => $dto->warehouse_id,
                    'parent_location_id' => $dto->parent_location_id,
                    'code'               => $dto->code,
                    'name'               => $dto->name,
                    'aisle'              => $dto->aisle,
                    'rack'               => $dto->rack,
                    'shelf'              => $dto->shelf,
                    'status'             => $dto->status,
                    'created_by'         => Context::get('user_id'),
                    'row_version'        => 1,
                ]);

                $this->dispatchOutboxEvent('inventory.location.created.v1', $location, $tenantId);

                return $location;
            });
        } catch (Exception $e) {
            Log::error('Failed to create Location: ' . $e->getMessage());
            throw $e;
        }
    }

    public function updateLocation(string $id, UpdateLocationDTO $dto): Location
    {
        try {
            return DB::transaction(function () use ($id, $dto) {
                $location = Location::findOrFail($id);
                $tenantId = Context::get('tenant_id');

                if ($dto->parent_location_id) {
                    if ($dto->parent_location_id === $location->location_id) {
                        throw ValidationException::withMessages([
                            'parent_location_id' => ['A location cannot be its own parent.'],
                        ]);
                    }
                    $parent = Location::findOrFail($dto->parent_location_id);
                    if ($parent->warehouse_id !== $location->warehouse_id) {
                        throw ValidationException::withMessages([
                            'parent_location_id' => ['Parent location must belong to the same warehouse.'],
                        ]);
                    }
                }

                $updateData = array_filter([
                    'name'   => $dto->name,
                    'aisle'  => $dto->aisle,
                    'rack'   => $dto->rack,
                    'shelf'  => $dto->shelf,
                    'status' => $dto->status,
                    'updated_by' => Context::get('user_id'),
                ], fn ($value) => !is_null($value));

                if ($dto->clear_parent) {
                    $updateData['parent_location_id'] = null;
                } elseif ($dto->parent_location_id !== null) {
                    $updateData['parent_location_id'] = $dto->parent_location_id;
                }

                $updateData['row_version'] = ((int) ($location->row_version ?? 1)) + 1;

                $location->update($updateData);

                $this->dispatchOutboxEvent('inventory.location.updated.v1', $location, $tenantId);

                return $location->fresh();
            });
        } catch (Exception $e) {
            Log::error('Failed to update Location: ' . $e->getMessage());
            throw $e;
        }
    }

    public function deleteLocation(string $id): void
    {
        try {
            DB::transaction(function () use ($id) {
                $location = Location::findOrFail($id);
                $tenantId = Context::get('tenant_id');

                $location->update(['deleted_by' => Context::get('user_id')]);
                $location->delete();

                $this->dispatchOutboxEvent('inventory.location.deleted.v1', $location, $tenantId);
            });
        } catch (Exception $e) {
            Log::error('Failed to delete Location: ' . $e->getMessage());
            throw $e;
        }
    }

    private function dispatchOutboxEvent(string $eventType, Location $location, string $tenantId): void
    {
        DB::table('event_outbox')->insert([
            'event_id'       => Str::uuid()->toString(),
            'tenant_id'      => $tenantId,
            'aggregate_type' => 'inv_locations',
            'aggregate_id'   => $location->location_id,
            'event_type'     => $eventType,
            'payload'        => json_encode($location->toArray()),
            'status'         => 1,
            'created_at'     => now(),
        ]);
    }
}
