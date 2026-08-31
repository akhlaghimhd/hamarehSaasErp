<?php

namespace App\Modules\MasterData\Services;

use App\Modules\MasterData\Models\Currency;
use App\Modules\MasterData\DTOs\CreateCurrencyDTO;
use App\Modules\MasterData\DTOs\UpdateCurrencyDTO;
use Illuminate\Database\Eloquent\Collection;
use Exception;

class CurrencyService
{
    public function getAll(): Collection
    {
        return Currency::query()->orderBy('code')->get();
    }

    public function getById(string $id): Currency
    {
        return Currency::query()->where('currency_id', $id)->firstOrFail();
    }

    public function create(CreateCurrencyDTO $dto): Currency
    {
        if (Currency::query()->where('code', $dto->code)->exists()) {
            throw new Exception('Currency code already exists.');
        }

        if ($dto->isDefault) {
            Currency::query()->where('is_default', true)->update(['is_default' => false]);
        }

        return Currency::create([
            'code'       => $dto->code,
            'name'       => $dto->name,
            'symbol'     => $dto->symbol,
            'is_default' => $dto->isDefault,
            'status'     => $dto->status,
        ]);
    }

    public function update(string $id, UpdateCurrencyDTO $dto): Currency
    {
        $currency = $this->getById($id);

        if ($dto->code !== null && $dto->code !== $currency->code) {
            if (Currency::query()->where('code', $dto->code)->where('currency_id', '!=', $id)->exists()) {
                throw new Exception('Currency code already exists.');
            }
        }

        $data = array_filter([
            'code'       => $dto->code,
            'name'       => $dto->name,
            'symbol'     => $dto->symbol,
            'is_default' => $dto->isDefault,
            'status'     => $dto->status,
        ], fn ($value) => $value !== null);

        if (($data['is_default'] ?? false) === true) {
            Currency::query()
                ->where('is_default', true)
                ->where('currency_id', '!=', $id)
                ->update(['is_default' => false]);
        }

        $currency->update($data);

        return $currency->fresh();
    }

    public function delete(string $id): void
    {
        $currency = $this->getById($id);
        $currency->delete();
    }
}
