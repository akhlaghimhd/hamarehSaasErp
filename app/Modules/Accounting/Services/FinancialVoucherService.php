<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\DTOs\FinancialVoucherDTO;
use App\Modules\Accounting\DTOs\UpdateFinancialVoucherDTO;
use App\Modules\Accounting\Models\FinancialVoucher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Status: 1 = Draft, 2 = Posted, 3 = Cancelled
 */
class FinancialVoucherService
{
    public const STATUS_DRAFT     = 1;
    public const STATUS_POSTED    = 2;
    public const STATUS_CANCELLED = 3;

    public function getAll(): Collection
    {
        return FinancialVoucher::with('items')->orderByDesc('voucher_date')->get();
    }

    public function getById(string $id): FinancialVoucher
    {
        return FinancialVoucher::with('items')->findOrFail($id);
    }

    public function createVoucher(FinancialVoucherDTO $dto): FinancialVoucher
    {
        return DB::transaction(function () use ($dto) {
            $tenantId = Context::get('tenant_id');
            $userId   = Context::get('user_id');

            $exists = FinancialVoucher::where('reference_number', $dto->referenceNumber)->exists();
            if ($exists) {
                throw new ConflictHttpException("Reference number '{$dto->referenceNumber}' already exists in this tenant.");
            }

            $voucher = FinancialVoucher::create([
                'tenant_id'        => $tenantId,
                'voucher_date'     => $dto->voucherDate,
                'description'      => $dto->description,
                'total_amount'     => $dto->totalAmount,
                'reference_number' => $dto->referenceNumber,
                'currency_id'      => $dto->currencyId,
                'status'           => self::STATUS_DRAFT,
                'created_by'       => $userId,
                'row_version'      => 1,
            ]);

            DB::table('event_outbox')->insert([
                'event_id'       => (string) Str::uuid(),
                'tenant_id'      => $tenantId,
                'aggregate_type' => 'fin_vouchers',
                'aggregate_id'   => $voucher->voucher_id,
                'event_type'     => 'accounting.voucher.created.v1',
                'payload'        => json_encode([
                    'voucher_id'       => $voucher->voucher_id,
                    'reference_number' => $voucher->reference_number,
                    'total_amount'     => $voucher->total_amount,
                    'status'           => $voucher->status,
                ]),
                'status'         => 1,
                'retry_count'    => 0,
                'created_at'     => now(),
            ]);

            return $voucher;
        });
    }

    public function updateVoucher(string $id, UpdateFinancialVoucherDTO $dto): FinancialVoucher
    {
        return DB::transaction(function () use ($id, $dto) {
            $tenantId = Context::get('tenant_id');
            $userId   = Context::get('user_id');

            $voucher = FinancialVoucher::findOrFail($id);

            if ((int) $voucher->status !== self::STATUS_DRAFT) {
                throw new ConflictHttpException('Only draft vouchers can be updated.');
            }

            if ($dto->referenceNumber !== null && $dto->referenceNumber !== $voucher->reference_number) {
                $exists = FinancialVoucher::where('reference_number', $dto->referenceNumber)
                    ->where('voucher_id', '!=', $id)
                    ->exists();
                if ($exists) {
                    throw new ConflictHttpException("Reference number '{$dto->referenceNumber}' already exists in this tenant.");
                }
            }

            $updateData = array_filter([
                'voucher_date'     => $dto->voucherDate,
                'description'      => $dto->description,
                'total_amount'     => $dto->totalAmount,
                'reference_number' => $dto->referenceNumber,
                'currency_id'      => $dto->currencyId,
                'updated_by'       => $userId,
            ], fn ($value) => $value !== null);

            $updateData['row_version'] = ((int) ($voucher->row_version ?? 1)) + 1;

            $voucher->update($updateData);

            DB::table('event_outbox')->insert([
                'event_id'       => (string) Str::uuid(),
                'tenant_id'      => $tenantId,
                'aggregate_type' => 'fin_vouchers',
                'aggregate_id'   => $voucher->voucher_id,
                'event_type'     => 'accounting.voucher.updated.v1',
                'payload'        => json_encode([
                    'voucher_id'  => $voucher->voucher_id,
                    'row_version' => $voucher->row_version,
                ]),
                'status'         => 1,
                'retry_count'    => 0,
                'created_at'     => now(),
            ]);

            return $voucher->fresh('items');
        });
    }

    /**
     * Post a draft voucher (Draft → Posted).
     * Domain: at least one line, debit total must equal credit total (Double-Entry).
     */
    public function postVoucher(string $id): FinancialVoucher
    {
        return DB::transaction(function () use ($id) {
            $tenantId = Context::get('tenant_id');
            $userId   = Context::get('user_id');

            $voucher = FinancialVoucher::with('items')->findOrFail($id);

            if ((int) $voucher->status !== self::STATUS_DRAFT) {
                throw new ConflictHttpException('Only draft vouchers can be posted.');
            }

            if ($voucher->items->isEmpty()) {
                throw new ConflictHttpException('Voucher must have at least one line item before posting.');
            }

            $totalDebit  = (float) $voucher->items->sum('debit');
            $totalCredit = (float) $voucher->items->sum('credit');

            if (round($totalDebit, 4) !== round($totalCredit, 4)) {
                throw new ConflictHttpException(
                    'Voucher lines are not balanced (debit=' . $totalDebit . ', credit=' . $totalCredit . ').'
                );
            }

            $voucher->update([
                'status'       => self::STATUS_POSTED,
                'total_amount' => $totalDebit,
                'updated_by'   => $userId,
                'row_version'  => ((int) ($voucher->row_version ?? 1)) + 1,
            ]);

            DB::table('event_outbox')->insert([
                'event_id'       => (string) Str::uuid(),
                'tenant_id'      => $tenantId,
                'aggregate_type' => 'fin_vouchers',
                'aggregate_id'   => $voucher->voucher_id,
                'event_type'     => 'accounting.voucher.posted.v1',
                'payload'        => json_encode([
                    'voucher_id'       => $voucher->voucher_id,
                    'reference_number' => $voucher->reference_number,
                    'total_amount'     => $totalDebit,
                    'posted_by'        => $userId,
                ]),
                'status'         => 1,
                'retry_count'    => 0,
                'created_at'     => now(),
            ]);

            return $voucher->fresh('items');
        });
    }

    public function deleteVoucher(string $id): void
    {
        DB::transaction(function () use ($id) {
            $tenantId = Context::get('tenant_id');
            $userId   = Context::get('user_id');

            $voucher = FinancialVoucher::findOrFail($id);

            if ((int) $voucher->status !== self::STATUS_DRAFT) {
                throw new ConflictHttpException('Only draft vouchers can be deleted.');
            }

            $voucher->update(['deleted_by' => $userId]);
            $voucher->delete();

            DB::table('event_outbox')->insert([
                'event_id'       => (string) Str::uuid(),
                'tenant_id'      => $tenantId,
                'aggregate_type' => 'fin_vouchers',
                'aggregate_id'   => $id,
                'event_type'     => 'accounting.voucher.deleted.v1',
                'payload'        => json_encode([
                    'voucher_id'       => $id,
                    'reference_number' => $voucher->reference_number,
                ]),
                'status'         => 1,
                'retry_count'    => 0,
                'created_at'     => now(),
            ]);
        });
    }
}
