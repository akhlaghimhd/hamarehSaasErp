<?php

namespace App\Modules\MasterData\Services;

use App\Modules\MasterData\Models\MasterDataValue;
use App\Modules\MasterData\DTOs\CreateMasterDataValueDTO;
use App\Modules\MasterData\DTOs\UpdateMasterDataValueDTO;
use Illuminate\Database\Eloquent\Collection;

class MasterDataValueService
{
    public function getAll(): Collection
    {
        return MasterDataValue::all();
    }

    public function getById(string $id): MasterDataValue
    {
        return MasterDataValue::findOrFail($id);
    }

    public function create(CreateMasterDataValueDTO $dto): MasterDataValue
    {
        return MasterDataValue::create((array) $dto);
    }

    public function update(string $id, UpdateMasterDataValueDTO $dto): MasterDataValue
    {
        $value = $this->getById($id);
        $data = array_filter((array) $dto, fn($v) => $v !== null);
        
        $value->update($data);
        return $value;
    }

    public function delete(string $id): void
    {
        $value = $this->getById($id);
        $value->delete();
    }
}