<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Accounting\Services\VoucherPostingService;
use App\Modules\Inventory\Models\InventoryDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * L6-INV-11 — Bridge Inventory → Accounting via VoucherPostingService (in-process, no HTTP).
 *
 * Account resolution is by tenant chart-of-accounts codes (convention):
 *   1200 Inventory Asset | 2100 GR/IR Clearing | 5100 COGS | 5200 Inventory Adjustment
 * If required accounts are missing, posting is skipped with a warning (stock still posts).
 */
class InventoryAccountingService
{
    public const CODE_INVENTORY_ASSET = '1200';
    public const CODE_GRIR_CLEARING    = '2100';
    public const CODE_COGS             = '5100';
    public const CODE_ADJUSTMENT       = '5200';

    public function __construct(
        private readonly VoucherPostingService $voucherPosting,
    ) {
    }

    /**
     * Post a balanced financial voucher for a posted inventory document.
     * Returns voucher_id or null when skipped (zero value / transfer / accounts missing).
     */
    public function postForDocument(InventoryDocument $document): ?string
    {
        $type = (int) $document->document_type;

        // Transfer is quantity movement only — no P&L / balance sheet impact in v1
        if ($type === InventoryDocumentService::TYPE_TRANSFER) {
            return null;
        }

        $amount = $this->totalValue($document);
        if ($amount <= 0) {
            return null;
        }

        $accounts = $this->resolveAccounts($document->tenant_id);
        if ($accounts === null) {
            Log::warning('InventoryAccountingService: required GL accounts not found; skipping voucher.', [
                'document_id' => $document->document_id,
                'document_type' => $type,
            ]);
            return null;
        }

        [$header, $lines] = $this->buildVoucherPayload($document, $amount, $accounts);

        return $this->voucherPosting->postVoucher($header, $lines);
    }

    /**
     * Reverse the accounting voucher linked to a voided inventory document.
     */
    public function reverseForDocument(InventoryDocument $document, string $reason = 'Inventory document voided'): ?string
    {
        if (empty($document->accounting_voucher_id)) {
            return null;
        }

        return $this->voucherPosting->reverseVoucher(
            $document->accounting_voucher_id,
            $reason . ' (' . $document->document_number . ')'
        );
    }

    /**
     * @return array{asset: string, clearing: string, cogs: string, adjustment: string}|null
     */
    private function resolveAccounts(string $tenantId): ?array
    {
        $codes = [
            'asset'      => self::CODE_INVENTORY_ASSET,
            'clearing'   => self::CODE_GRIR_CLEARING,
            'cogs'       => self::CODE_COGS,
            'adjustment' => self::CODE_ADJUSTMENT,
        ];

        $rows = DB::table('fin_accounts')
            ->where('tenant_id', $tenantId)
            ->whereIn('code', array_values($codes))
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->get(['account_id', 'code']);

        if ($rows->count() < 4) {
            return null;
        }

        $byCode = $rows->keyBy('code');
        foreach ($codes as $key => $code) {
            if (!$byCode->has($code)) {
                return null;
            }
        }

        return [
            'asset'      => $byCode[self::CODE_INVENTORY_ASSET]->account_id,
            'clearing'   => $byCode[self::CODE_GRIR_CLEARING]->account_id,
            'cogs'       => $byCode[self::CODE_COGS]->account_id,
            'adjustment' => $byCode[self::CODE_ADJUSTMENT]->account_id,
        ];
    }

    private function totalValue(InventoryDocument $document): float
    {
        $sum = 0.0;
        foreach ($document->items as $line) {
            $sum += (float) $line->quantity * (float) $line->unit_cost;
        }

        return round($sum, 4);
    }

    /**
     * @param  array{asset: string, clearing: string, cogs: string, adjustment: string}  $accounts
     * @return array{0: array, 1: array}
     */
    private function buildVoucherPayload(InventoryDocument $document, float $amount, array $accounts): array
    {
        $type = (int) $document->document_type;
        $ref  = $document->document_number;
        $desc = 'Inventory ' . $this->typeLabel($type) . ' ' . $ref;

        $header = [
            'voucher_date'       => optional($document->posting_date)->toDateString()
                ?? now()->toDateString(),
            'description'        => $document->description ?: $desc,
            'reference_number'   => 'INV-' . $ref,
            'status'             => 2,
            'source_module'      => 'inventory',
            'source_document_id' => $document->document_id,
        ];

        $lines = match ($type) {
            InventoryDocumentService::TYPE_RECEIPT => [
                [
                    'account_id'  => $accounts['asset'],
                    'debit'       => $amount,
                    'credit'      => 0,
                    'description' => 'Inventory receipt ' . $ref,
                ],
                [
                    'account_id'  => $accounts['clearing'],
                    'debit'       => 0,
                    'credit'      => $amount,
                    'description' => 'GR/IR clearing ' . $ref,
                ],
            ],
            InventoryDocumentService::TYPE_ISSUE => [
                [
                    'account_id'  => $accounts['cogs'],
                    'debit'       => $amount,
                    'credit'      => 0,
                    'description' => 'COGS issue ' . $ref,
                ],
                [
                    'account_id'  => $accounts['asset'],
                    'debit'       => 0,
                    'credit'      => $amount,
                    'description' => 'Inventory issue ' . $ref,
                ],
            ],
            InventoryDocumentService::TYPE_ADJUSTMENT => $this->adjustmentLines($document, $accounts, $ref),
            default => throw new InvalidArgumentException("Unsupported document type for accounting: {$type}"),
        };

        return [$header, $lines];
    }

    /**
     * @param  array{asset: string, clearing: string, cogs: string, adjustment: string}  $accounts
     */
    private function adjustmentLines(InventoryDocument $document, array $accounts, string $ref): array
    {
        $net = 0.0;
        foreach ($document->items as $line) {
            $value = (float) $line->quantity * (float) $line->unit_cost;
            if (!empty($line->to_location_id) && empty($line->from_location_id)) {
                $net += $value;
            } elseif (!empty($line->from_location_id) && empty($line->to_location_id)) {
                $net -= $value;
            } else {
                $net += $value;
            }
        }
        $net = round($net, 4);
        $abs = abs($net);

        if ($net >= 0) {
            return [
                [
                    'account_id'  => $accounts['asset'],
                    'debit'       => $abs,
                    'credit'      => 0,
                    'description' => 'Inventory adjustment increase ' . $ref,
                ],
                [
                    'account_id'  => $accounts['adjustment'],
                    'debit'       => 0,
                    'credit'      => $abs,
                    'description' => 'Adjustment offset ' . $ref,
                ],
            ];
        }

        return [
            [
                'account_id'  => $accounts['adjustment'],
                'debit'       => $abs,
                'credit'      => 0,
                'description' => 'Adjustment expense ' . $ref,
            ],
            [
                'account_id'  => $accounts['asset'],
                'debit'       => 0,
                'credit'      => $abs,
                'description' => 'Inventory adjustment decrease ' . $ref,
            ],
        ];
    }

    private function typeLabel(int $type): string
    {
        return match ($type) {
            InventoryDocumentService::TYPE_RECEIPT    => 'Receipt',
            InventoryDocumentService::TYPE_ISSUE      => 'Issue',
            InventoryDocumentService::TYPE_TRANSFER   => 'Transfer',
            InventoryDocumentService::TYPE_ADJUSTMENT => 'Adjustment',
            default => 'Document',
        };
    }
}
