<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Application\Services;

use App\Modules\FinancialAccounting\Infrastructure\Models\MoodianSubmission;
use App\Modules\FinancialAccounting\Infrastructure\Models\TaxTransaction;
use Illuminate\Support\Facades\DB;

/**
 * FIN-P2-06 / P2-07 — VAT period summary + ledger (tax tx) vs Moodian recon.
 */
class TaxReportingService
{
    /**
     * @return array{
     *   company_id: string,
     *   from: string,
     *   to: string,
     *   by_code: list<array{tax_code: string, direction: string, txn_count: int, taxable: string, tax: string}>,
     *   totals: array{output_tax: string, input_tax: string, net_payable: string, txn_count: int}
     * }
     */
    public function vatPeriodSummary(string $companyId, string $from, string $to): array
    {
        $rows = TaxTransaction::query()
            ->where('company_id', $companyId)
            ->whereDate('transaction_date', '>=', $from)
            ->whereDate('transaction_date', '<=', $to)
            ->select([
                'tax_code',
                'direction',
                DB::raw('COUNT(*) as txn_count'),
                DB::raw('COALESCE(SUM(taxable_amount),0) as taxable'),
                DB::raw('COALESCE(SUM(tax_amount),0) as tax'),
            ])
            ->groupBy('tax_code', 'direction')
            ->orderBy('tax_code')
            ->orderBy('direction')
            ->get();

        $byCode = [];
        $output = 0.0;
        $input = 0.0;
        $count = 0;

        foreach ($rows as $r) {
            $tax = (float) $r->tax;
            $count += (int) $r->txn_count;
            if ($r->direction === TaxTransaction::DIR_OUTPUT) {
                $output += $tax;
            } else {
                $input += $tax;
            }
            $byCode[] = [
                'tax_code'  => (string) $r->tax_code,
                'direction' => (string) $r->direction,
                'txn_count' => (int) $r->txn_count,
                'taxable'   => number_format((float) $r->taxable, 4, '.', ''),
                'tax'       => number_format($tax, 4, '.', ''),
            ];
        }

        return [
            'company_id' => $companyId,
            'from'       => $from,
            'to'         => $to,
            'by_code'    => $byCode,
            'totals'     => [
                'output_tax'  => number_format($output, 4, '.', ''),
                'input_tax'   => number_format($input, 4, '.', ''),
                'net_payable' => number_format($output - $input, 4, '.', ''),
                'txn_count'   => $count,
            ],
        ];
    }

    /**
     * Compare tax transactions in window vs Moodian submissions for same company.
     *
     * @return array{
     *   company_id: string,
     *   from: string,
     *   to: string,
     *   tax_txn_count: int,
     *   tax_amount_total: string,
     *   moodian: array{pending: int, submitted: int, accepted: int, rejected: int, failed: int, total: int},
     *   gaps: list<array{tax_transaction_id: string, tax_code: string, tax_amount: string, transaction_date: string, reason: string}>,
     *   gap_count: int
     * }
     */
    public function moodianLedgerRecon(string $companyId, string $from, string $to): array
    {
        $txns = TaxTransaction::query()
            ->where('company_id', $companyId)
            ->whereDate('transaction_date', '>=', $from)
            ->whereDate('transaction_date', '<=', $to)
            ->orderBy('transaction_date')
            ->get();

        $subs = MoodianSubmission::query()
            ->where('company_id', $companyId)
            ->get();

        $byTaxTxn = $subs->keyBy('tax_transaction_id');
        $bySource = $subs->groupBy(fn ($s) => $s->source_document_type.'|'.$s->source_document_id);

        $moodianCounts = [
            'pending'   => 0,
            'submitted' => 0,
            'accepted'  => 0,
            'rejected'  => 0,
            'failed'    => 0,
            'total'     => $subs->count(),
        ];
        foreach ($subs as $s) {
            $st = strtoupper((string) $s->status);
            $key = match ($st) {
                'PENDING' => 'pending',
                'SUBMITTED' => 'submitted',
                'ACCEPTED' => 'accepted',
                'REJECTED' => 'rejected',
                'FAILED' => 'failed',
                default => null,
            };
            if ($key) {
                $moodianCounts[$key]++;
            }
        }

        $gaps = [];
        $taxTotal = 0.0;

        foreach ($txns as $tx) {
            $taxTotal += (float) $tx->tax_amount;
            $linked = null;
            if ($tx->tax_transaction_id && $byTaxTxn->has($tx->tax_transaction_id)) {
                $linked = $byTaxTxn->get($tx->tax_transaction_id);
            } else {
                $key = $tx->source_document_type.'|'.$tx->source_document_id;
                $linked = $bySource->get($key)?->first();
            }

            if (! $linked) {
                $gaps[] = [
                    'tax_transaction_id' => (string) $tx->tax_transaction_id,
                    'tax_code'           => (string) $tx->tax_code,
                    'tax_amount'         => number_format((float) $tx->tax_amount, 4, '.', ''),
                    'transaction_date'   => $tx->transaction_date?->toDateString() ?? (string) $tx->transaction_date,
                    'reason'             => 'MISSING_MOODIAN_SUBMISSION',
                ];
                continue;
            }

            $st = strtoupper((string) $linked->status);
            if (in_array($st, ['REJECTED', 'FAILED', 'CANCELLED'], true)) {
                $gaps[] = [
                    'tax_transaction_id' => (string) $tx->tax_transaction_id,
                    'tax_code'           => (string) $tx->tax_code,
                    'tax_amount'         => number_format((float) $tx->tax_amount, 4, '.', ''),
                    'transaction_date'   => $tx->transaction_date?->toDateString() ?? (string) $tx->transaction_date,
                    'reason'             => 'MOODIAN_STATUS_'.$st,
                ];
            }
        }

        return [
            'company_id'       => $companyId,
            'from'             => $from,
            'to'               => $to,
            'tax_txn_count'    => $txns->count(),
            'tax_amount_total' => number_format($taxTotal, 4, '.', ''),
            'moodian'          => $moodianCounts,
            'gaps'             => $gaps,
            'gap_count'        => count($gaps),
        ];
    }
}
