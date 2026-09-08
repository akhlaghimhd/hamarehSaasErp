<?php

namespace App\Modules\ProcurementSales\Services;

use App\Modules\Accounting\Services\FiscalPeriodService;
use App\Modules\Accounting\Services\VoucherPostingService;
use App\Modules\ProcurementSales\Models\PurchaseReceipt;
use App\Modules\ProcurementSales\Models\SalesDeliveryOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * L6-PS-06 / L6-PS-07 / L6-PS-08 — Formal accounting bridge for Procurement & Sales.
 *
 * Account codes:
 *   1200 Inventory | 2100 GR/IR | 2000 AP | 1100 AR | 4000 Revenue | 5100 COGS | 5200 Adj | 1000 Bank
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
    public const CODE_BANK             = '1000';

    public function __construct(
        private readonly VoucherPostingService $voucherPosting,
        private readonly FiscalPeriodService $fiscalPeriodService,
    ) {
    }

    public function resolveFiscalPeriodId(?string $date = null): ?string
    {
        return $this->fiscalPeriodService->resolveOpenPeriodIdForDate($date);
    }

    public function postForPurchaseReceipt(PurchaseReceipt $receipt): ?string
    {
        Log::info('ProcurementSalesAccountingService: postForPurchaseReceipt invoked', [
            'purchase_receipt_id' => $receipt->purchase_receipt_id,
        ]);
        return null;
    }

    public function postForSalesDelivery(SalesDeliveryOrder $delivery): ?string
    {
        Log::info('ProcurementSalesAccountingService: postForSalesDelivery invoked', [
            'delivery_order_id' => $delivery->delivery_order_id,
        ]);
        return null;
    }

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
            ['account_id' => $accounts[self::CODE_GRIR_CLEARING], 'debit' => $amount, 'credit' => 0, 'description' => 'GR/IR clear ' . $ref],
            ['account_id' => $accounts[self::CODE_ACCOUNTS_PAYABLE], 'debit' => 0, 'credit' => $amount, 'description' => 'AP ' . $ref],
        ];

        $payloadHeader = array_merge([
            'description' => $header['description'] ?? ('Purchase invoice clearing ' . $ref),
            'reference_number' => $ref,
            'voucher_date' => $header['voucher_date'] ?? now()->toDateString(),
            'source_module' => 'procurement_sales',
            'source_document_id' => $header['source_document_id'] ?? null,
            'status' => 1,
        ], $header);

        return $this->voucherPosting->postVoucher($payloadHeader, $lines);
    }

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
            ['account_id' => $accounts[self::CODE_ACCOUNTS_RECEIVABLE], 'debit' => $amount, 'credit' => 0, 'description' => 'AR ' . $ref],
            ['account_id' => $accounts[self::CODE_SALES_REVENUE], 'debit' => 0, 'credit' => $amount, 'description' => 'Revenue ' . $ref],
        ];

        $payloadHeader = array_merge([
            'description' => $header['description'] ?? ('Sales invoice ' . $ref),
            'reference_number' => $ref,
            'voucher_date' => $header['voucher_date'] ?? now()->toDateString(),
            'source_module' => 'procurement_sales',
            'source_document_id' => $header['source_document_id'] ?? null,
            'status' => 1,
        ], $header);

        return $this->voucherPosting->postVoucher($payloadHeader, $lines);
    }

    /**
 * AR receipt: Dr Bank, Cr AR.
     */
    public function postArReceipt(array $header, float $amount, string $tenantId): ?string
    {
        $amount = round($amount, 4);
        if ($amount <= 0) {
            return null;
        }

        $accounts = $this->resolveAccounts($tenantId, [self::CODE_BANK, self::CODE_ACCOUNTS_RECEIVABLE]);
        if ($accounts === null) {
            Log::warning('ProcurementSalesAccountingService: Bank or AR accounts missing; skipping AR receipt voucher.');
            return null;
        }

        $ref = $header['reference_number'] ?? 'PS-AR-RCPT';
        $lines = [
            ['account_id' => $accounts[self::CODE_BANK], 'debit' => $amount, 'credit' => 0, 'description' => 'Bank receipt ' . $ref],
            ['account_id' => $accounts[self::CODE_ACCOUNTS_RECEIVABLE], 'debit' => 0, 'credit' => $amount, 'description' => 'AR collection ' . $ref],
        ];

        $payloadHeader = array_merge([
            'description' => $header['description'] ?? ('AR receipt ' . $ref),
            'reference_number' => $ref,
            'voucher_date' => $header['voucher_date'] ?? now()->toDateString(),
            'source_module' => 'procurement_sales',
            'source_document_id' => $header['source_document_id'] ?? null,
            'status' => 1,
        ], $header);

        return $this->voucherPosting->postVoucher($payloadHeader, $lines);
    }

    /**
 * AP payment: Dr AP, Cr Bank.
     */
    public function postApPayment(array $header, float $amount, string $tenantId): ?string
    {
        $amount = round($amount, 4);
        if ($amount <= 0) {
            return null;
        }

        $accounts = $this->resolveAccounts($tenantId, [self::CODE_ACCOUNTS_PAYABLE, self::CODE_BANK]);
        if ($accounts === null) {
            Log::warning('ProcurementSalesAccountingService: AP or Bank accounts missing; skipping AP payment voucher.');
            return null;
        }

        $ref = $header['reference_number'] ?? 'PS-AP-PAY';
        $lines = [
            ['account_id' => $accounts[self::CODE_ACCOUNTS_PAYABLE], 'debit' => $amount, 'credit' => 0, 'description' => 'AP settlement ' . $ref],
            ['account_id' => $accounts[self::CODE_BANK], 'debit' => 0, 'credit' => $amount, 'description' => 'Bank payment ' . $ref],
        ];

        $payloadHeader = array_merge([
            'description' => $header['description'] ?? ('AP payment ' . $ref),
            'reference_number' => $ref,
            'voucher_date' => $header['voucher_date'] ?? now()->toDateString(),
            'source_module' => 'procurement_sales',
            'source_document_id' => $header['source_document_id'] ?? null,
            'status' => 1,
        ], $header);

        return $this->voucherPosting->postVoucher($payloadHeader, $lines);
    }

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
