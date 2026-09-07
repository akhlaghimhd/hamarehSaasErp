<?php

namespace App\Modules\ProcurementSales\Services;

use App\Modules\Accounting\Services\FiscalPeriodService;
use App\Modules\Accounting\Services\VoucherPostingService;
use App\Modules\ProcurementSales\Models\PurchaseReceipt;
use App\Modules\ProcurementSales\Models\SalesDeliveryOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * L6-PS-06 — Formal direct accounting bridge for Procurement & Sales.
 *
 * Owns commercial (AP/AR / clearing coordination) mapping to Accounting via
 * VoucherPostingService (in-process, no HTTP — Modular Monolith rule).
 *
 * Stock valuation vouchers remain owned by Inventory (InventoryAccountingService).
 * This service:
 *   - Resolves open fiscal periods for cross-module document creation.
 *   - Posts commercial-side vouchers when PS documents carry pure financial impact
 *     (prepared for future Purchase Invoice / Sales Invoice; current Receipt/Delivery
 *      value impact is already posted on the Inventory document).
 *   - Uses the same COA code conventions as Inventory for consistency.
 *
 * Account codes (tenant chart-of-accounts convention):
 *   1200 Inventory Asset | 2100 GR/IR Clearing | 2000 Accounts Payable
 *   1100 Accounts Receivable | 4000 Sales Revenue | 5100 COGS | 5200 Adjustment
 */
class ProcurementSalesAccountingService
{
    public const CODE_INVENTORY_ASSET = '1200';
    public const CODE_GRIR_CLEARING    = '2100';
    public const CODE_ACCOUNTS_PAYABLE = '2000';
    public const CODE_ACCOUNTS_RECEIVABLE = '1100';
    public const CODE_SALES_REVENUE    = '4000';
    public const CODE_COGS             = '5100';
    public const CODE_ADJUSTMENT       = '5200';

    public function __construct(
        private readonly VoucherPostingService $voucherPosting,
        private readonly FiscalPeriodService $fiscalPeriodService,
    ) {
    }

    /**
     * Resolve an open fiscal period ID for the given date (or today).
     * Primary entry point used by Inventory Goods Receipt path (L6-PS-06 wiring).
     */
    public function resolveFiscalPeriodId(?string $date = null): ?string
    {
        return $this->fiscalPeriodService->resolveOpenPeriodIdForDate($date);
    }

    /**
     * Formal post for a posted Purchase Receipt (commercial side).
     *
     * Current scope: value is already reflected on the Inventory Goods Receipt voucher
     * (Dr Asset / Cr GR/IR). This method is the formal PS-owned hook for future AP
     * Invoice matching and any additional commercial accrual. Returns null when no
     * extra voucher is required (or accounts missing).
     */
    public function postForPurchaseReceipt(PurchaseReceipt $receipt): ?string
    {
        // Value impact is owned by Inventory document posting (L6-INV-11).
        // Keep this method as the stable contract for future Purchase Invoice / accrual.
        Log::info('ProcurementSalesAccountingService: postForPurchaseReceipt invoked (commercial value on Inventory path)', [
            'purchase_receipt_id' => $receipt->purchase_receipt_id,
            'receipt_number'      => $receipt->receipt_number,
        ]);

        return null;
    }

    /**
     * Formal post for a posted Sales Delivery (commercial side).
     * COGS is posted by Inventory Issue; revenue/AR reserved for Sales Invoice.
     */
    public function postForSalesDelivery(SalesDeliveryOrder $delivery): ?string
    {
        Log::info('ProcurementSalesAccountingService: postForSalesDelivery invoked (COGS on Inventory path)', [
            'delivery_order_id' => $delivery->delivery_order_id,
            'delivery_number'   => $delivery->delivery_number,
        ]);

        return null;
    }

    /**
     * Post a balanced voucher for a future Purchase Invoice style document
     * (Dr GR/IR Clearing, Cr Accounts Payable). Ready for when Invoice entities land.
     *
     * @param  array{reference_number?: string, voucher_date?: string, description?: string, source_document_id?: string}  $header
     * @param  float  $amount  Positive total
     */
    public function postPurchaseInvoiceClearing(array $header, float $amount, string $tenantId): ?string
    {
        $amount = round($amount, 4);
        if ($amount <= 0) {
            return null;
        }

        $accounts = $this->resolveAccounts($tenantId, [
            self::CODE_GRIR_CLEARING,
            self::CODE_ACCOUNTS_PAYABLE,
        ]);
        if ($accounts === null) {
            Log::warning('ProcurementSalesAccountingService: GR/IR or AP accounts missing; skipping purchase invoice clearing voucher.');
            return null;
        }

        $ref = $header['reference_number'] ?? 'PS-PINV';
        $lines = [
            [
                'account_id'  => $accounts[self::CODE_GRIR_CLEARING],
                'debit'       => $amount,
                'credit'      => 0,
                'description' => 'GR/IR clear ' . $ref,
            ],
            [
                'account_id'  => $accounts[self::CODE_ACCOUNTS_PAYABLE],
                'debit'       => 0,
                'credit'      => $amount,
                'description' => 'AP ' . $ref,
            ],
        ];

        $payloadHeader = array_merge([
            'description'        => $header['description'] ?? ('Purchase invoice clearing ' . $ref),
            'reference_number'   => $ref,
            'voucher_date'       => $header['voucher_date'] ?? now()->toDateString(),
            'source_module'      => 'procurement_sales',
            'source_document_id' => $header['source_document_id'] ?? null,
            'status'             => 1,
        ], $header);

        return $this->voucherPosting->postVoucher($payloadHeader, $lines);
    }

    /**
     * Post a balanced voucher for a future Sales Invoice style document
     * (Dr AR, Cr Sales Revenue). Ready for when Invoice entities land.
     *
     * @param  array{reference_number?: string, voucher_date?: string, description?: string, source_document_id?: string}  $header
     */
    public function postSalesInvoice(array $header, float $amount, string $tenantId): ?string
    {
        $amount = round($amount, 4);
        if ($amount <= 0) {
            return null;
        }

        $accounts = $this->resolveAccounts($tenantId, [
            self::CODE_ACCOUNTS_RECEIVABLE,
            self::CODE_SALES_REVENUE,
        ]);
        if ($accounts === null) {
            Log::warning('ProcurementSalesAccountingService: AR or Revenue accounts missing; skipping sales invoice voucher.');
            return null;
        }

        $ref = $header['reference_number'] ?? 'PS-SINV';
        $lines = [
            [
                'account_id'  => $accounts[self::CODE_ACCOUNTS_RECEIVABLE],
                'debit'       => $amount,
                'credit'      => 0,
                'description' => 'AR ' . $ref,
            ],
            [
                'account_id'  => $accounts[self::CODE_SALES_REVENUE],
                'debit'       => 0,
                'credit'      => $amount,
                'description' => 'Revenue ' . $ref,
            ],
        ];

        $payloadHeader = array_merge([
            'description'        => $header['description'] ?? ('Sales invoice ' . $ref),
            'reference_number'   => $ref,
            'voucher_date'       => $header['voucher_date'] ?? now()->toDateString(),
            'source_module'      => 'procurement_sales',
            'source_document_id' => $header['source_document_id'] ?? null,
            'status'             => 1,
        ], $header);

        return $this->voucherPosting->postVoucher($payloadHeader, $lines);
    }

    /**
     * @param  list<string>  $requiredCodes
     * @return array<string, string>|null  code => account_id
     */
    private function resolveAccounts(string $tenantId, array $requiredCodes): ?array
    {
        $rows = DB::table('fin_accounts')
            ->where('tenant_id', $tenantId)
            ->whereIn('code', $requiredCodes)
            ->where('is_active', true)
            ->get(['account_id', 'code']);

        $map = [];
        foreach ($rows as $row) {
            $map[$row->code] = $row->account_id;
        }

        foreach ($requiredCodes as $code) {
            if (empty($map[$code])) {
                return null;
            }
        }

        return $map;
    }
}
