<?php

namespace App\Modules\MasterData\Services;

use App\Modules\MasterData\Models\UnitOfMeasure;
use App\Modules\MasterData\DTOs\CreateUnitOfMeasureDTO;
use App\Modules\MasterData\DTOs\UpdateUnitOfMeasureDTO;
use Illuminate\Database\Eloquent\Collection;

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
        return UnitOfMeasure::create((array) $dto);
    }

    public function update(string $id, UpdateUnitOfMeasureDTO $dto): UnitOfMeasure
    {
        $unit = $this->getById($id);
        $data = array_filter((array) $dto, fn($value) => $value !== null);
        
        $unit->update($data);
        return $unit;
    }

    public function delete(string $id): void
    {
        $unit = $this->getById($id);
        $unit->delete();
    }
}