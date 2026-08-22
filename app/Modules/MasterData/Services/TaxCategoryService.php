<?php

namespace App\Modules\MasterData\Services;

use App\Modules\MasterData\Models\TaxCategory;
use App\Modules\MasterData\DTOs\CreateTaxCategoryDTO;
use App\Modules\MasterData\DTOs\UpdateTaxCategoryDTO;
use Illuminate\Database\Eloquent\Collection;

class TaxCategoryService
{
    public function getAll(): Collection
    {
        return TaxCategory::all();
    }

    public function getById(string $id): TaxCategory
    {
        return TaxCategory::findOrFail($id);
    }

    public function create(CreateTaxCategoryDTO $dto): TaxCategory
    {
        return TaxCategory::create((array) $dto);
    }

    public function update(string $id, UpdateTaxCategoryDTO $dto): TaxCategory
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