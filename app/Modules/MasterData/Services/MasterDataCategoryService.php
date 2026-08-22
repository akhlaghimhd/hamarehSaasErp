<?php

namespace App\Modules\MasterData\Services;

use App\Modules\MasterData\Models\MasterDataCategory;
use App\Modules\MasterData\DTOs\CreateMasterDataCategoryDTO;
use App\Modules\MasterData\DTOs\UpdateMasterDataCategoryDTO;
use Illuminate\Database\Eloquent\Collection;

class MasterDataCategoryService
{
    public function getAll(): Collection
    {
        return MasterDataCategory::all();
    }

    public function getById(string $id): MasterDataCategory
    {
        return MasterDataCategory::findOrFail($id);
    }

    public function create(CreateMasterDataCategoryDTO $dto): MasterDataCategory
    {
        return MasterDataCategory::create((array) $dto);
    }

    public function update(string $id, UpdateMasterDataCategoryDTO $dto): MasterDataCategory
    {
        $category = $this->getById($id);
        $data = array_filter((array) $dto, fn($value) => $value !== null);
        
        $category->update($data);
        return $category;
    }

    public function delete(string $id): void
    {
        $category = $this->getById($id);
        $category->delete();
    }
}