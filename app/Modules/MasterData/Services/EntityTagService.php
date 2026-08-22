<?php

namespace App\Modules\MasterData\Services;

use App\Modules\MasterData\Models\EntityTag;
use App\Modules\MasterData\DTOs\CreateEntityTagDTO;
use Illuminate\Database\Eloquent\Collection;

class EntityTagService
{
    public function getAll(): Collection
    {
        return EntityTag::all();
    }

    public function assignTag(CreateEntityTagDTO $dto): EntityTag
    {
        return EntityTag::create((array) $dto);
    }

    public function removeTag(string $id): void
    {
        $entityTag = EntityTag::findOrFail($id);
        $entityTag->delete();
    }
}