<?php

namespace App\Modules\MasterData\Services;

use App\Modules\MasterData\Models\Tag;
use App\Modules\MasterData\DTOs\CreateTagDTO;
use App\Modules\MasterData\DTOs\UpdateTagDTO;
use Illuminate\Database\Eloquent\Collection;

class TagService
{
    public function getAll(): Collection
    {
        return Tag::all();
    }

    public function getById(string $id): Tag
    {
        return Tag::findOrFail($id);
    }

    public function create(CreateTagDTO $dto): Tag
    {
        return Tag::create((array) $dto);
    }

    public function update(string $id, UpdateTagDTO $dto): Tag
    {
        $tag = $this->getById($id);
        $data = array_filter((array) $dto, fn($value) => $value !== null);
        
        $tag->update($data);
        return $tag;
    }

    public function delete(string $id): void
    {
        $tag = $this->getById($id);
        $tag->delete();
    }
}