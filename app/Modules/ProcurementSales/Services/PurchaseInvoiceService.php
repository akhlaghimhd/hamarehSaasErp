<?php

namespace App\Modules\ProcurementSales\Services;

use App\Modules\ProcurementSales\DTOs\CreatePurchaseInvoiceDTO;
use App\Modules\ProcurementSales\Models\PurchaseInvoice;
use App\Modules\ProcurementSales\Models\PurchaseInvoiceItem;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * L6-PS-07 – Purchase Invoice create (Draft) and post (→ AP / GR-IR clearing voucher).
 */
class PurchaseInvoiceService
{
    public const STATUS_DRAFT = 1;
    public const STATUS_OPEN = 2;
    public const STATUS_PAID = 3;
    public const STATUS_VOIDED = 4;

    public function __construct(
        private readonly ProcurementSalesAccountingService $accountingService,
    ) {
    }

    public function create(CreatePurchaseInvoiceDTO $dto): PurchaseInvoice
    {
        return DB::transaction(function () use ($dto) {
            $tenantId = Context::get('tenant_id');
            $userId = Context::get('user_id');

            if (!$tenantId) {
                throw new \RuntimeException('Tenant Context is missing.');
            }
            if (empty($dto->items)) {
                throw new ConflictHttpException('Purchase invoice must have at least one line.');
            }

            $subtotal = 0.0;
            $taxTotal = 0.0;
            $discountTotal = 0.0;

            foreach ($dto->items as $item) {
                $lineNet = ($item->quantity * $item->unitPrice) - $item->discountAmount;
                $subtotal += $lineNet;
                $taxTotal += $item->taxAmount;
                $discountTotal += $item->discountAmount;
            }

            $total = round($subtotal + $taxTotal, 4);
            $invoiceNumber = 'PINV-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));

            $invoice = PurchaseInvoice::create([
                'tenant_id'            => $tenantId,
                'invoice_number'       => $invoiceNumber,
                'supplier_id'          => $dto->supplierId,
                'purchase_order_id'    => $dto->purchaseOrderId,
                'currency_id'          => $dto->currencyId,
                'invoice_date'         => $dto->invoiceDate,
                'due_date'             => $dto->dueDate,
                'subtotal_amount'      => round($subtotal, 4),
                'discount_amount'      => round($discountTotal, 4),
                'tax_amount'           => round($taxTotal, 4),
                'total_amount'         => $total,
                'status'               => self::STATUS_DRAFT,
                'tax_invoice_number'   => $dto->taxInvoiceNumber,
                'supplier_invoice_ref' => $dto->supplierInvoiceRef,
                'description'          => $dto->description,
                'created_by'           => $userId,
                'updated_by'           => $userId,
                'row_version'          => 1,
            ]);

            $lineNumber = 1;
            foreach ($dto->items as $item) {
                $lineNet = ($item->quantity * $item->unitPrice) - $item->discountAmount;
                PurchaseInvoiceItem::create([
                    'tenant_id'            => $tenantId,
                    'purchase_invoice_id'  => $invoice->purchase_invoice_id,
                    'item_id'              => $item->itemId,
                    'quantity'             => $item->quantity,
                    'unit_price'           => $item->unitPrice,
                    'discount_amount'      => $item->discountAmount,
                    'tax_amount'           => $item->taxAmount,
                    'total_price'          => round($lineNet + $item->taxAmount, 4),
                    'tax_definition_id'    => $item->taxDefinitionId,
                    'uom_code'             => $item->uomCode,
                    'line_number'          => $item->lineNumber ?: $lineNumber,
                    'description'          => $item->description,
                    'created_by'           => $userId,
                    'updated_by'           => $userId,
                    'row_version'          => 1,
                ]);
                $lineNumber++;
            }

            return $invoice->load('items');
        });
    }

    public function getById(string $id): PurchaseInvoice
    {
        $invoice = PurchaseInvoice::query()->with('items')->find($id);
        if (!$invoice) {
            throw new NotFoundHttpException('Purchase invoice not found.');
        }
        return $invoice;
    }

    public function post(string $id): PurchaseInvoice
    {
        return DB::transaction(function () use ($id) {
            $tenantId = Context::get('tenant_id');
            $userId = Context::get('user_id');

            if (!$tenantId) {
                throw new \RuntimeException('Tenant Context is missing.');
            }

            $invoice = PurchaseInvoice::query()->lockForUpdate()->with('items')->find($id);
            if (!$invoice) {
                throw new NotFoundHttpException('Purchase invoice not found.');
            }

            if ((int) $invoice->status !== self::STATUS_DRAFT) {
                throw new ConflictHttpException('Only draft purchase invoices can be posted.');
            }

            if ($invoice->items->isEmpty()) {
                throw new ConflictHttpException('Cannot post a purchase invoice with no lines.');
            }

            $amount = round((float) $invoice->total_amount, 4);
            if ($amount <= 0) {
                throw new ConflictHttpException('Purchase invoice total must be positive to post.');
            }

            $periodId = $this->accountingService->resolveFiscalPeriodId(
                $invoice->invoice_date?->toDateString() ?? now()->toDateString()
            );

            $voucherId = $this->accountingService->postPurchaseInvoiceClearing([
                'reference_number'   => $invoice->invoice_number,
                'voucher_date'       => $invoice->invoice_date?->toDateString() ?? now()->toDateString(),
                'description'        => $invoice->description ?? ('Purchase invoice ' . $invoice->invoice_number),
                'source_document_id' => $invoice->purchase_invoice_id,
            ], $amount, $tenantId);

            $invoice->update([
                'status'                => self::STATUS_OPEN,
                'posting_date'          => now()->toDateString(),
                'fiscal_period_id'      => $periodId,
                'accounting_voucher_id' => $voucherId,
                'updated_by'            => $userId,
                'row_version'           => ((int) ($invoice->row_version ?? 1)) + 1,
            ]);

            Log::info('PurchaseInvoice posted', [
                'purchase_invoice_id' => $invoice->purchase_invoice_id,
                'voucher_id'          => $voucherId,
                'amount'              => $amount,
            ]);

            return $invoice->fresh(['items']);
        });
    }
}
