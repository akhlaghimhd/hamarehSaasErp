<?php

namespace App\Modules\MasterData\Services;

use App\Modules\MasterData\Models\BankAccount;
use App\Modules\MasterData\DTOs\CreateBankAccountDTO;
use App\Modules\MasterData\DTOs\UpdateBankAccountDTO;
use Illuminate\Database\Eloquent\Collection;

class BankAccountService
{
    public function getAll(): Collection
    {
        return BankAccount::all();
    }

    public function getById(string $id): BankAccount
    {
        return BankAccount::findOrFail($id);
    }

    public function create(CreateBankAccountDTO $dto): BankAccount
    {
        return BankAccount::create((array) $dto);
    }

    public function update(string $id, UpdateBankAccountDTO $dto): BankAccount
    {
        $account = $this->getById($id);
        $data = array_filter((array) $dto, fn($value) => $value !== null);
        
        $account->update($data);
        return $account;
    }

    public function delete(string $id): void
    {
        $account = $this->getById($id);
        $account->delete();
    }
}