<?php

namespace App\Modules\MasterData\Services;

use App\Modules\MasterData\Models\TaxDefinition;
use App\Modules\MasterData\DTOs\CreateTaxDefinitionDTO;
use App\Modules\MasterData\DTOs\UpdateTaxDefinitionDTO;
use Illuminate\Database\Eloquent\Collection;

class TaxDefinitionService
{
    public function getAll(): Collection
    {
        return TaxDefinition::all();
    }

    public function getById(string $id): TaxDefinition
    {
        return TaxDefinition::findOrFail($id);
    }

    public function create(CreateTaxDefinitionDTO $dto): TaxDefinition
    {
        return TaxDefinition::create((array) $dto);
    }

    public function update(string $id, UpdateTaxDefinitionDTO $dto): TaxDefinition
    {
        $definition = $this->getById($id);
        $data = array_filter((array) $dto, fn($value) => $value !== null);
        
        $definition->update($data);
        return $definition;
    }

    public function delete(string $id): void
    {
        $definition = $this->getById($id);
        $definition->delete();
    }
}