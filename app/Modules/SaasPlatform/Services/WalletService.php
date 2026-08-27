<?php

namespace App\Modules\SaasPlatform\Services;

use App\Modules\SaasPlatform\Models\TenantWallet;
use App\Modules\SaasPlatform\Models\TenantWalletTransaction;
use App\Modules\SaasPlatform\Models\Tenant;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WalletService
{
    public const TYPE_CREDIT = 1;
    public const TYPE_DEBIT  = 2;

    /**
     * Get or create wallet for a tenant.
     */
    public function getOrCreateWallet(string $tenantId, ?string $createdBy = null): TenantWallet
    {
        $wallet = TenantWallet::where('tenant_id', $tenantId)->whereNull('deleted_at')->first();

        if ($wallet) {
            return $wallet;
        }

        Tenant::where('tenant_id', $tenantId)->whereNull('deleted_at')->firstOrFail();

        return TenantWallet::create([
            'tenant_id'  => $tenantId,
            'balance'    => 0,
            'status'     => 1,
            'created_by' => $createdBy,
            'updated_by' => $createdBy,
        ]);
    }

    /**
     * Credit (increase) wallet balance.
     */
    public function credit(string $tenantId, float $amount, ?string $description = null, ?string $createdBy = null): TenantWalletTransaction
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Credit amount must be positive.');
        }

        return DB::transaction(function () use ($tenantId, $amount, $description, $createdBy) {
            $wallet = $this->getOrCreateWallet($tenantId, $createdBy);

            $wallet->balance = $wallet->balance + $amount;
            $wallet->updated_by = $createdBy;
            $wallet->save();

            return TenantWalletTransaction::create([
                'wallet_id'        => $wallet->wallet_id,
                'transaction_type' => self::TYPE_CREDIT,
                'amount'           => $amount,
                'balance_after'    => $wallet->balance,
                'description'      => $description ?? 'Credit',
                'created_by'       => $createdBy,
                'updated_by'       => $createdBy,
            ]);
        });
    }

    /**
     * Debit (decrease) wallet balance.
     */
    public function debit(string $tenantId, float $amount, ?string $description = null, ?string $createdBy = null): TenantWalletTransaction
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Debit amount must be positive.');
        }

        return DB::transaction(function () use ($tenantId, $amount, $description, $createdBy) {
            $wallet = $this->getOrCreateWallet($tenantId, $createdBy);

            if ($wallet->balance < $amount) {
                throw new InvalidArgumentException('Insufficient wallet balance.');
            }

            $wallet->balance = $wallet->balance - $amount;
            $wallet->updated_by = $createdBy;
            $wallet->save();

            return TenantWalletTransaction::create([
                'wallet_id'        => $wallet->wallet_id,
                'transaction_type' => self::TYPE_DEBIT,
                'amount'           => $amount,
                'balance_after'    => $wallet->balance,
                'description'      => $description ?? 'Debit',
                'created_by'       => $createdBy,
                'updated_by'       => $createdBy,
            ]);
        });
    }

    /**
     * Get current balance.
     */
    public function getBalance(string $tenantId): float
    {
        $wallet = $this->getOrCreateWallet($tenantId);
        return (float) $wallet->balance;
    }
}