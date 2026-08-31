<?php

namespace App\Modules\PartnerLayer\Services;

use App\Modules\PartnerLayer\Models\Partner;
use App\Modules\PartnerLayer\Models\PartnerBankAccount;
use App\Modules\PartnerLayer\DTOs\CreatePartnerBankAccountDTO;
use App\Modules\PartnerLayer\DTOs\UpdatePartnerBankAccountDTO;
use App\Base\Context\TenantContext;
use Exception;
use Illuminate\Support\Collection;

/** P3-S8 — Partner bank accounts for payout settlement. */
class PartnerBankAccountService
{
    public function getAccounts(?string $partnerId = null): Collection
    {
        $query = PartnerBankAccount::query()->orderBy('created_at', 'desc');

        if ($partnerId) {
            $this->assertPartnerAccessible($partnerId);
            $query->where('partner_id', $partnerId);
        } else {
            $ids = $this->accessiblePartnerIds();
            if ($ids->isEmpty()) {
                return collect();
            }
            $query->whereIn('partner_id', $ids);
        }

        return $query->get();
    }

    public function getAccountById(string $accountId): PartnerBankAccount
    {
        $account = PartnerBankAccount::query()
            ->where('partner_bank_account_id', $accountId)
            ->firstOrFail();

        $this->assertPartnerAccessible($account->partner_id);

        return $account;
    }

    public function createAccount(CreatePartnerBankAccountDTO $dto): PartnerBankAccount
    {
        $this->assertPartnerAccessible($dto->partnerId);

        $exists = PartnerBankAccount::query()
            ->where('shaba_number', $dto->shabaNumber)
            ->where('is_active', true)
            ->exists();

        if ($exists) {
            throw new Exception('An active bank account with this SHABA number already exists.');
        }

        return PartnerBankAccount::create([
            'partner_id'     => $dto->partnerId,
            'bank_name'      => $dto->bankName,
            'account_number' => $dto->accountNumber,
            'shaba_number'   => $dto->shabaNumber,
            'card_number'    => $dto->cardNumber,
            'is_active'      => $dto->isActive,
        ]);
    }

    public function updateAccount(string $accountId, UpdatePartnerBankAccountDTO $dto): PartnerBankAccount
    {
        $account = $this->getAccountById($accountId);

        if ($dto->shabaNumber !== null && $dto->shabaNumber !== $account->shaba_number) {
            $exists = PartnerBankAccount::query()
                ->where('shaba_number', $dto->shabaNumber)
                ->where('is_active', true)
                ->where('partner_bank_account_id', '!=', $accountId)
                ->exists();

            if ($exists) {
                throw new Exception('An active bank account with this SHABA number already exists.');
            }
        }

        $account->update([
            'bank_name'      => $dto->bankName ?? $account->bank_name,
            'account_number' => array_key_exists('account_number', $dto->raw)
                ? $dto->accountNumber
                : $account->account_number,
            'shaba_number'   => $dto->shabaNumber ?? $account->shaba_number,
            'card_number'    => array_key_exists('card_number', $dto->raw)
                ? $dto->cardNumber
                : $account->card_number,
            'is_active'      => $dto->isActive ?? $account->is_active,
        ]);

        return $account->fresh();
    }

    public function deleteAccount(string $accountId): void
    {
        $account = $this->getAccountById($accountId);
        $account->delete();
    }

    private function assertPartnerAccessible(string $partnerId): void
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $query = Partner::query()->where('partner_id', $partnerId);

        if ($tenantId) {
            $query->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
            });
        }

        if (!$query->exists()) {
            throw new Exception('Partner not found or not accessible in this context.');
        }
    }

    private function accessiblePartnerIds(): Collection
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        $query = Partner::query()->select('partner_id');

        if ($tenantId) {
            $query->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
            });
        }

        return $query->pluck('partner_id');
    }
}
