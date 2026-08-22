<?php

namespace App\Modules\MasterData\Services;

use App\Modules\MasterData\Models\EntityContactPoint;
use App\Modules\MasterData\DTOs\CreateEntityContactPointDTO;
use App\Modules\MasterData\DTOs\UpdateEntityContactPointDTO;
use Illuminate\Database\Eloquent\Collection;

class EntityContactPointService
{
    public function getAll(): Collection
    {
        return EntityContactPoint::all();
    }

    public function getById(string $id): EntityContactPoint
    {
        return EntityContactPoint::findOrFail($id);
    }

    public function create(CreateEntityContactPointDTO $dto): EntityContactPoint
    {
        return EntityContactPoint::create((array) $dto);
    }

    public function update(string $id, UpdateEntityContactPointDTO $dto): EntityContactPoint
    {
        $contact = $this->getById($id);
        $data = array_filter((array) $dto, fn($value) => $value !== null);
        
        $contact->update($data);
        return $contact;
    }

    public function delete(string $id): void
    {
        $contact = $this->getById($id);
        $contact->delete();
    }
}