<?php

namespace App\Modules\PartnerLayer\Services;

use App\Modules\PartnerLayer\Models\Partner;
use App\Modules\PartnerLayer\Models\PartnerBankAccount;
use App\Modules\PartnerLayer\Models\PartnerPayout;
use App\Modules\PartnerLayer\DTOs\CreatePartnerPayoutDTO;
use App\Modules\PartnerLayer\DTOs\UpdatePartnerPayoutDTO;
use App\Base\Context\TenantContext;
use Exception;
use Illuminate\Support\Collection;

/**
 * P3-S7 — Partner payouts / settlements.
 *
 * currency_id: logical reference (no cross-module FK).
 * bank_account_id: optional reference within PartnerLayer BC.
 * payout_number: unique while not soft-deleted.
 */
class PartnerPayoutService
{
    public function getPayouts(?string $partnerId = null): Collection
    {
        $query = PartnerPayout::query()->orderBy('created_at', 'desc');

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

    public function getPayoutById(string $payoutId): PartnerPayout
    {
        $payout = PartnerPayout::query()
            ->where('payout_id', $payoutId)
            ->firstOrFail();

        $this->assertPartnerAccessible($payout->partner_id);

        return $payout;
    }

    public function createPayout(CreatePartnerPayoutDTO $dto): PartnerPayout
    {
        $this->assertPartnerAccessible($dto->partnerId);

        $exists = PartnerPayout::query()
            ->where('payout_number', $dto->payoutNumber)
            ->exists();

        if ($exists) {
            throw new Exception('Payout number already exists.');
        }

        if ((float) $dto->totalAmount < 0) {
            throw new Exception('total_amount must be non-negative.');
        }

        if ($dto->bankAccountId) {
            $this->assertBankAccountBelongsToPartner($dto->bankAccountId, $dto->partnerId);
        }

        return PartnerPayout::create([
            'partner_id'         => $dto->partnerId,
            'payout_number'      => $dto->payoutNumber,
            'total_amount'       => $dto->totalAmount,
            'currency_id'        => $dto->currencyId,
            'bank_account_id'    => $dto->bankAccountId,
            'payout_date'        => $dto->payoutDate,
            'payment_reference'  => $dto->paymentReference,
            'status'             => $dto->status,
            'description'        => $dto->description,
        ]);
    }

    public function updatePayout(string $payoutId, UpdatePartnerPayoutDTO $dto): PartnerPayout
    {
        $payout = $this->getPayoutById($payoutId);

        if ($dto->payoutNumber !== null && $dto->payoutNumber !== $payout->payout_number) {
            $exists = PartnerPayout::query()
                ->where('payout_number', $dto->payoutNumber)
                ->where('payout_id', '!=', $payoutId)
                ->exists();

            if ($exists) {
                throw new Exception('Payout number already exists.');
            }
        }

        if ($dto->bankAccountId !== null) {
            $this->assertBankAccountBelongsToPartner($dto->bankAccountId, $payout->partner_id);
        }

        $payout->update([
            'payout_number'     => $dto->payoutNumber ?? $payout->payout_number,
            'total_amount'      => $dto->totalAmount ?? $payout->total_amount,
            'currency_id'       => $dto->currencyId ?? $payout->currency_id,
            'bank_account_id'   => array_key_exists('bank_account_id', $dto->raw)
                ? $dto->bankAccountId
                : $payout->bank_account_id,
            'payout_date'       => array_key_exists('payout_date', $dto->raw)
                ? $dto->payoutDate
                : $payout->payout_date,
            'payment_reference' => array_key_exists('payment_reference', $dto->raw)
                ? $dto->paymentReference
                : $payout->payment_reference,
            'status'            => $dto->status ?? $payout->status,
            'description'       => array_key_exists('description', $dto->raw)
                ? $dto->description
                : $payout->description,
        ]);

        return $payout->fresh();
    }

    public function deletePayout(string $payoutId): void
    {
        $payout = $this->getPayoutById($payoutId);
        $payout->delete();
    }

    private function assertBankAccountBelongsToPartner(string $bankAccountId, string $partnerId): void
    {
        $account = PartnerBankAccount::query()
            ->where('partner_bank_account_id', $bankAccountId)
            ->where('partner_id', $partnerId)
            ->first();

        if (!$account) {
            throw new Exception('Bank account not found for this partner.');
        }
    }

    private function assertPartnerAccessible(string $partnerId): void
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        $query = Partner::query()->where('partner_id', $partnerId);

        if ($tenantId) {
            $query->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)
                  ->orWhereNull('tenant_id');
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
                $q->where('tenant_id', $tenantId)
                  ->orWhereNull('tenant_id');
            });
        }

        return $query->pluck('partner_id');
    }
}
