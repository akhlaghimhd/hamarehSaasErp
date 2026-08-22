<?php

namespace App\Modules\MasterData\Services;

use App\Modules\MasterData\Models\EntityAddress;
use App\Modules\MasterData\DTOs\CreateEntityAddressDTO;
use App\Modules\MasterData\DTOs\UpdateEntityAddressDTO;
use Illuminate\Database\Eloquent\Collection;

class EntityAddressService
{
    public function getAll(): Collection
    {
        return EntityAddress::all();
    }

    public function getById(string $id): EntityAddress
    {
        return EntityAddress::findOrFail($id);
    }

    public function create(CreateEntityAddressDTO $dto): EntityAddress
    {
        return EntityAddress::create((array) $dto);
    }

    public function update(string $id, UpdateEntityAddressDTO $dto): EntityAddress
    {
        $address = $this->getById($id);
        $data = array_filter((array) $dto, fn($value) => $value !== null);
        
        $address->update($data);
        return $address;
    }

    public function delete(string $id): void
    {
        $address = $this->getById($id);
        $address->delete();
    }
}