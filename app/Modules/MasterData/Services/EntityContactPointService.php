<?php

namespace App\Modules\MasterData\Services;

use App\Modules\MasterData\Models\EntityContactPoint;
use App\Modules\MasterData\DTOs\CreateEntityContactPointDTO;
use App\Modules\MasterData\DTOs\UpdateEntityContactPointDTO;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class EntityContactPointService
{
    public function getAll(?string $entityType = null, ?string $entityId = null): Collection
    {
        $q = EntityContactPoint::query();

        if ($entityType) {
            $q->where('entity_type', $entityType);
        }
        if ($entityId) {
            $q->where('entity_id', $entityId);
        }

        return $q->orderByDesc('is_primary')->orderBy('created_at')->get();
    }

    public function getById(string $id): EntityContactPoint
    {
        return EntityContactPoint::findOrFail($id);
    }

    public function create(CreateEntityContactPointDTO $dto): EntityContactPoint
    {
        return DB::transaction(function () use ($dto) {
            if ($dto->is_primary) {
                $this->clearPrimary($dto->entity_type, $dto->entity_id);
            }

            return EntityContactPoint::create([
                'entity_type' => $dto->entity_type,
                'entity_id' => $dto->entity_id,
                'contact_type' => $dto->contact_type,
                'contact_value' => $dto->contact_value,
                'extension' => $dto->extension,
                'is_primary' => $dto->is_primary,
                'status' => $dto->status,
            ]);
        });
    }

    public function update(string $id, UpdateEntityContactPointDTO $dto): EntityContactPoint
    {
        return DB::transaction(function () use ($id, $dto) {
            $contact = $this->getById($id);
            $data = array_filter([
                'contact_type' => $dto->contact_type,
                'contact_value' => $dto->contact_value,
                'extension' => $dto->extension,
                'is_primary' => $dto->is_primary,
                'status' => $dto->status,
            ], fn ($value) => $value !== null);

            if (($data['is_primary'] ?? false) === true) {
                $this->clearPrimary($contact->entity_type, $contact->entity_id, $contact->contact_point_id);
            }

            $contact->update($data);

            return $contact->fresh();
        });
    }

    public function delete(string $id): void
    {
        $contact = $this->getById($id);
        $contact->delete();
    }

    private function clearPrimary(string $entityType, string $entityId, ?string $exceptId = null): void
    {
        $q = EntityContactPoint::query()
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('is_primary', true);
        if ($exceptId) {
            $q->where('contact_point_id', '!=', $exceptId);
        }
        $q->update(['is_primary' => false]);
    }
}
