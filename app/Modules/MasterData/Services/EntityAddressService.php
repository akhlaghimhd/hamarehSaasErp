<?php

namespace App\Modules\MasterData\Services;

use App\Modules\MasterData\Models\EntityAddress;
use App\Modules\MasterData\DTOs\CreateEntityAddressDTO;
use App\Modules\MasterData\DTOs\UpdateEntityAddressDTO;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class EntityAddressService
{
    public function getAll(?string $entityType = null, ?string $entityId = null): Collection
    {
        $q = EntityAddress::query();

        if ($entityType) {
            $q->where('entity_type', $entityType);
        }
        if ($entityId) {
            $q->where('entity_id', $entityId);
        }

        return $q->orderByDesc('is_primary')->orderBy('created_at')->get();
    }

    public function getById(string $id): EntityAddress
    {
        return EntityAddress::findOrFail($id);
    }

    public function create(CreateEntityAddressDTO $dto): EntityAddress
    {
        return DB::transaction(function () use ($dto) {
            if ($dto->is_primary) {
                $this->clearPrimary($dto->entity_type, $dto->entity_id);
            }

            return EntityAddress::create([
                'entity_type' => $dto->entity_type,
                'entity_id' => $dto->entity_id,
                'address_text' => $dto->address_text,
                'address_type_id' => $dto->address_type_id,
                'country_id' => $dto->country_id,
                'province_id' => $dto->province_id,
                'city_id' => $dto->city_id,
                'postal_code' => $dto->postal_code,
                'is_primary' => $dto->is_primary,
                'status' => $dto->status,
            ]);
        });
    }

    public function update(string $id, UpdateEntityAddressDTO $dto): EntityAddress
    {
        return DB::transaction(function () use ($id, $dto) {
            $address = $this->getById($id);
            $data = array_filter([
                'address_text' => $dto->address_text,
                'address_type_id' => $dto->address_type_id,
                'country_id' => $dto->country_id,
                'province_id' => $dto->province_id,
                'city_id' => $dto->city_id,
                'postal_code' => $dto->postal_code,
                'is_primary' => $dto->is_primary,
                'status' => $dto->status,
            ], fn ($value) => $value !== null);

            if (($data['is_primary'] ?? false) === true) {
                $this->clearPrimary($address->entity_type, $address->entity_id, $address->entity_address_id);
            }

            $address->update($data);

            return $address->fresh();
        });
    }

    public function delete(string $id): void
    {
        $address = $this->getById($id);
        $address->delete();
    }

    private function clearPrimary(string $entityType, string $entityId, ?string $exceptId = null): void
    {
        $q = EntityAddress::query()
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('is_primary', true);
        if ($exceptId) {
            $q->where('entity_address_id', '!=', $exceptId);
        }
        $q->update(['is_primary' => false]);
    }
}
