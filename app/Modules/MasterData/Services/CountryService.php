<?php

namespace App\Modules\MasterData\Services;

use App\Modules\MasterData\Models\Country;
use App\Modules\MasterData\DTOs\CreateCountryDTO;
use App\Modules\MasterData\DTOs\UpdateCountryDTO;
use Illuminate\Database\Eloquent\Collection;

class CountryService
{
    public function getAll(): Collection
    {
        return Country::all();
    }

    public function getById(string $id): Country
    {
        return Country::findOrFail($id);
    }

    public function create(CreateCountryDTO $dto): Country
    {
        return Country::create((array) $dto);
    }

    public function update(string $id, UpdateCountryDTO $dto): Country
    {
        $country = $this->getById($id);
        
        // فیلتر کردن مقادیر null برای جلوگیری از بازنویسی فیلدهای ارسال نشده
        $data = array_filter((array) $dto, fn($value) => $value !== null);
        
        $country->update($data);
        return $country;
    }

    public function delete(string $id): void
    {
        $country = $this->getById($id);
        $country->delete();
    }
}