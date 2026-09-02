<?php

namespace App\Modules\Accounting\Services;

use App\Contracts\Accounting\VoucherPostingContract;
use App\Modules\Accounting\Models\FinancialVoucher;
use App\Modules\Accounting\Models\FinancialVoucherItem;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Concrete implementation of VoucherPostingContract.
 * Allows other modules to post accounting vouchers without direct model coupling.
 */
class VoucherPostingService implements VoucherPostingContract
{
    public function postVoucher(array $header, array $lines): string
    {
        if (empty($lines)) {
            throw new InvalidArgumentException('Voucher must have at least one line.');
        }

        $tenantId = Context::get('tenant_id');
        $userId   = Context::get('user_id');

        return DB::transaction(function () use ($header, $lines, $tenantId, $userId) {
            $totalDebit  = 0;
            $totalCredit = 0;

            foreach ($lines as $line) {
                $totalDebit  += (float) ($line['debit'] ?? 0);
                $totalCredit += (float) ($line['credit'] ?? 0);
            }

            // Basic balance check
            if (round($totalDebit, 4) !== round($totalCredit, 4)) {
                throw new InvalidArgumentException('Voucher lines are not balanced (debit != credit).');
            }

            $voucher = FinancialVoucher::create([
                'tenant_id'        => $tenantId,
                'voucher_date'     => $header['voucher_date'] ?? now()->toDateString(),
                'description'      => $header['description'] ?? null,
                'total_amount'     => $totalDebit,
                'reference_number' => $header['reference_number'] ?? null,
                'currency_id'      => $header['currency_id'] ?? null,
                'status'           => $header['status'] ?? 1, // 1 = Draft by default
                'created_by'       => $userId,
                'row_version'      => 1,
            ]);

            foreach ($lines as $line) {
                FinancialVoucherItem::create([
                    'tenant_id'      => $tenantId,
                    'voucher_id'     => $voucher->voucher_id,
                    'account_id'     => $line['account_id'],
                    'debit'          => $line['debit'] ?? 0,
                    'credit'         => $line['credit'] ?? 0,
                    'cost_center_id' => $line['cost_center_id'] ?? null,
                    'description'    => $line['description'] ?? null,
                    'created_by'     => $userId,
                    'row_version'    => 1,
                ]);
            }

            // Outbox event
            DB::table('event_outbox')->insert([
                'event_id'       => (string) Str::uuid(),
                'tenant_id'      => $tenantId,
                'aggregate_type' => 'fin_vouchers',
                'aggregate_id'   => $voucher->voucher_id,
                'event_type'     => 'accounting.voucher.posted.v1',
                'payload'        => json_encode([
                    'voucher_id'       => $voucher->voucher_id,
                    'source_module'    => $header['source_module'] ?? null,
                    'source_document_id' => $header['source_document_id'] ?? null,
                    'total_amount'     => $totalDebit,
                ]),
                'status'         => 1,
                'retry_count'    => 0,
                'created_at'     => now(),
            ]);

            return $voucher->voucher_id;
        });
    }

    public function reverseVoucher(string $voucherId, string $reason): string
    {
        $original = FinancialVoucher::with('items')->findOrFail($voucherId);

        $header = [
            'voucher_date'       => now()->toDateString(),
            'description'        => 'Reversal of ' . $original->reference_number . ' – ' . $reason,
            'reference_number'   => 'REV-' . ($original->reference_number ?? $voucherId),
            'currency_id'        => $original->currency_id,
            'status'             => 2, // Posted
            'source_module'      => 'accounting',
            'source_document_id' => $voucherId,
        ];

        $lines = [];
        foreach ($original->items as $item) {
            $lines[] = [
                'account_id'     => $item->account_id,
                'debit'          => $item->credit, // swap
                'credit'         => $item->debit,  // swap
                'cost_center_id' => $item->cost_center_id,
                'description'    => 'Reversal: ' . ($item->description ?? ''),
            ];
        }

        return $this->postVoucher($header, $lines);
    }
}
