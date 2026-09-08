<?php

namespace App\Modules\ProcurementSales\Services;

use App\Modules\Accounting\Models\CashTransaction;
use App\Modules\Accounting\Models\PaymentSchedule;
use App\Modules\ProcurementSales\Models\PurchaseInvoice;
use App\Modules\ProcurementSales\Models\SalesInvoice;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PaymentSettlementService
{
    public function __construct(
        private readonly ProcurementSalesAccountingService $accountingService,
    ) {
    }

    public function ensureScheduleForSalesInvoice(SalesInvoice $invoice): PaymentSchedule
    {
        $existing = PaymentSchedule::query()
            ->where('source_document_type', PaymentSchedule::SOURCE_SAL_INVOICE)
            ->where('source_document_id', $invoice->sales_invoice_id)
            ->first();
        if ($existing) {
            return $existing;
        }
        $tenantId = Context::get('tenant_id') ?? $invoice->tenant_id;
        $userId = Context::get('user_id');
        return PaymentSchedule::create([
            'tenant_id' => $tenantId,
            'source_document_type' => PaymentSchedule::SOURCE_SAL_INVOICE,
            'source_document_id' => $invoice->sales_invoice_id,
            'currency_id' => $invoice->currency_id,
            'due_date' => $invoice->due_date?->toDateString() ?? $invoice->invoice_date?->toDateString() ?? now()->toDateString(),
            'expected_amount' => round((float) $invoice->total_amount, 4),
            'paid_amount' => 0,
            'status' => PaymentSchedule::STATUS_PENDING,
            'created_by' => $userId,
            'row_version' => 1,
        ]);
    }

    public function ensureScheduleForPurchaseInvoice(PurchaseInvoice $invoice): PaymentSchedule
    {
        $existing = PaymentSchedule::query()
            ->where('source_document_type', PaymentSchedule::SOURCE_PUR_INVOICE)
            ->where('source_document_id', $invoice->purchase_invoice_id)
            ->first();
        if ($existing) {
            return $existing;
        }
        $tenantId = Context::get('tenant_id') ?? $invoice->tenant_id;
        $userId = Context::get('user_id');
        return PaymentSchedule::create([
            'tenant_id' => $tenantId,
            'source_document_type' => PaymentSchedule::SOURCE_PUR_INVOICE,
            'source_document_id' => $invoice->purchase_invoice_id,
            'currency_id' => $invoice->currency_id,
            'due_date' => $invoice->due_date?->toDateString() ?? $invoice->invoice_date?->toDateString() ?? now()->toDateString(),
            'expected_amount' => round((float) $invoice->total_amount, 4),
            'paid_amount' => 0,
            'status' => PaymentSchedule::STATUS_PENDING,
            'created_by' => $userId,
            'row_version' => 1,
        ]);
    }

    public function recordReceipt(string $paymentScheduleId, string $bankAccountId, float $amount, ?string $paymentReference = null, ?string $transactionDate = null): CashTransaction
    {
        $amount = round($amount, 4);
        if ($amount <= 0) {
            throw new ConflictHttpException('Receipt amount must be positive.');
        }
        return DB::transaction(function () use ($paymentScheduleId, $bankAccountId, $amount, $paymentReference, $transactionDate) {
            $schedule = PaymentSchedule::query()->lockForUpdate()->find($paymentScheduleId);
            if (!$schedule) {
                throw new NotFoundHttpException('Payment schedule not found.');
            }
            if ($schedule->source_document_type !== PaymentSchedule::SOURCE_SAL_INVOICE) {
                throw new ConflictHttpException('Receipts are only allowed on sales invoice schedules.');
            }
            if ((int) $schedule->status === PaymentSchedule::STATUS_SETTLED) {
                throw new ConflictHttpException('Schedule is already settled.');
            }
            $remaining = round((float) $schedule->expected_amount - (float) $schedule->paid_amount, 4);
            if ($amount > $remaining + 0.0001) {
                throw new ConflictHttpException('Receipt exceeds remaining schedule amount.');
            }
            $tenantId = Context::get('tenant_id');
            $userId = Context::get('user_id');
            $voucherId = $this->accountingService->postArReceipt([
                'reference_number' => $paymentReference ?? ('AR-RCPT-' . substr($schedule->payment_schedule_id, 0, 8)),
                'voucher_date' => $transactionDate ?? now()->toDateString(),
                'description' => 'AR receipt for schedule ' . $schedule->payment_schedule_id,
                'source_document_id' => $schedule->source_document_id,
            ], $amount, $tenantId);
            $tx = CashTransaction::create([
                'tenant_id' => $tenantId,
                'payment_schedule_id' => $schedule->payment_schedule_id,
                'bank_account_id' => $bankAccountId,
                'transaction_type' => CashTransaction::TYPE_RECEIPT,
                'amount' => $amount,
                'payment_reference' => $paymentReference,
                'transaction_date' => $transactionDate ?? now(),
                'status' => CashTransaction::STATUS_CLEARED,
                'accounting_voucher_id' => $voucherId,
                'created_by' => $userId,
                'row_version' => 1,
            ]);
            $newPaid = round((float) $schedule->paid_amount + $amount, 4);
            $newStatus = $newPaid + 0.0001 >= (float) $schedule->expected_amount
                ? PaymentSchedule::STATUS_SETTLED : PaymentSchedule::STATUS_PARTIALLY_PAID;
            $schedule->update([
                'paid_amount' => $newPaid,
                'status' => $newStatus,
                'updated_by' => $userId,
                'row_version' => ((int) ($schedule->row_version ?? 1)) + 1,
            ]);
            $invoice = SalesInvoice::query()->lockForUpdate()->find($schedule->source_document_id);
            if ($invoice) {
                $invStatus = $newStatus === PaymentSchedule::STATUS_SETTLED
                    ? SalesInvoiceService::STATUS_FULLY_PAID : SalesInvoiceService::STATUS_PARTIALLY_PAID;
                $invoice->update([
                    'status' => $invStatus,
                    'updated_by' => $userId,
                    'row_version' => ((int) ($invoice->row_version ?? 1)) + 1,
                ]);
            }
            return $tx->fresh(['schedule']);
        });
    }

    public function recordPayment(string $paymentScheduleId, string $bankAccountId, float $amount, ?string $paymentReference = null, ?string $transactionDate = null): CashTransaction
    {
        $amount = round($amount, 4);
        if ($amount <= 0) {
            throw new ConflictHttpException('Payment amount must be positive.');
        }
        return DB::transaction(function () use ($paymentScheduleId, $bankAccountId, $amount, $paymentReference, $transactionDate) {
            $schedule = PaymentSchedule::query()->lockForUpdate()->find($paymentScheduleId);
            if (!$schedule) {
                throw new NotFoundHttpException('Payment schedule not found.');
            }
            if ($schedule->source_document_type !== PaymentSchedule::SOURCE_PUR_INVOICE) {
                throw new ConflictHttpException('Payments are only allowed on purchase invoice schedules.');
            }
            if ((int) $schedule->status === PaymentSchedule::STATUS_SETTLED) {
                throw new ConflictHttpException('Schedule is already settled.');
            }
            $remaining = round((float) $schedule->expected_amount - (float) $schedule->paid_amount, 4);
            if ($amount > $remaining + 0.0001) {
                throw new ConflictHttpException('Payment exceeds remaining schedule amount.');
            }
            $tenantId = Context::get('tenant_id');
            $userId = Context::get('user_id');
            $voucherId = $this->accountingService->postApPayment([
                'reference_number' => $paymentReference ?? ('AP-PAY-' . substr($schedule->payment_schedule_id, 0, 8)),
                'voucher_date' => $transactionDate ?? now()->toDateString(),
                'description' => 'AP payment for schedule ' . $schedule->payment_schedule_id,
                'source_document_id' => $schedule->source_document_id,
            ], $amount, $tenantId);
            $tx = CashTransaction::create([
                'tenant_id' => $tenantId,
                'payment_schedule_id' => $schedule->payment_schedule_id,
                'bank_account_id' => $bankAccountId,
                'transaction_type' => CashTransaction::TYPE_PAYMENT,
                'amount' => $amount,
                'payment_reference' => $paymentReference,
                'transaction_date' => $transactionDate ?? now(),
                'status' => CashTransaction::STATUS_CLEARED,
                'accounting_voucher_id' => $voucherId,
                'created_by' => $userId,
                'row_version' => 1,
            ]);
            $newPaid = round((float) $schedule->paid_amount + $amount, 4);
            $newStatus = $newPaid + 0.0001 >= (float) $schedule->expected_amount
                ? PaymentSchedule::STATUS_SETTLED : PaymentSchedule::STATUS_PARTIALLY_PAID;
            $schedule->update([
                'paid_amount' => $newPaid,
                'status' => $newStatus,
                'updated_by' => $userId,
                'row_version' => ((int) ($schedule->row_version ?? 1)) + 1,
            ]);
            $invoice = PurchaseInvoice::query()->lockForUpdate()->find($schedule->source_document_id);
            if ($invoice) {
                $invStatus = $newStatus === PaymentSchedule::STATUS_SETTLED
                    ? PurchaseInvoiceService::STATUS_FULLY_PAID : PurchaseInvoiceService::STATUS_PARTIALLY_PAID;
                $invoice->update([
                    'status' => $invStatus,
                    'updated_by' => $userId,
                    'row_version' => ((int) ($invoice->row_version ?? 1)) + 1,
                ]);
            }
            return $tx->fresh(['schedule']);
        });
    }

    public function markOverdueSchedules(?string $asOfDate = null): int
    {
        $asOf = $asOfDate ?? now()->toDateString();
        return PaymentSchedule::query()
            ->whereIn('status', [PaymentSchedule::STATUS_PENDING, PaymentSchedule::STATUS_PARTIALLY_PAID])
            ->whereDate('due_date', '<', $asOf)
            ->update(['status' => PaymentSchedule::STATUS_OVERDUE, 'updated_at' => now()]);
    }

    public function createInstallmentSchedulesForSalesInvoice(string $salesInvoiceId, array $installments)
    {
        return DB::transaction(function () use ($salesInvoiceId, $installments) {
            if (empty($installments)) {
                throw new ConflictHttpException('At least one installment is required.');
            }
            $invoice = SalesInvoice::query()->lockForUpdate()->find($salesInvoiceId);
            if (!$invoice) {
                throw new NotFoundHttpException('Sales invoice not found.');
            }
            if (!in_array((int) $invoice->status, [SalesInvoiceService::STATUS_OPEN, SalesInvoiceService::STATUS_PARTIALLY_PAID], true)) {
                throw new ConflictHttpException('Installments only allowed for open/partially-paid invoices.');
            }
            $sum = round(array_sum(array_map(fn ($i) => (float) $i['amount'], $installments)), 4);
            if (abs($sum - (float) $invoice->total_amount) > 0.01) {
                throw new ConflictHttpException('Installment amounts must sum to invoice total.');
            }
            PaymentSchedule::query()
                ->where('source_document_type', PaymentSchedule::SOURCE_SAL_INVOICE)
                ->where('source_document_id', $salesInvoiceId)
                ->where('paid_amount', 0)
                ->whereIn('status', [PaymentSchedule::STATUS_PENDING, PaymentSchedule::STATUS_OVERDUE])
                ->get()
                ->each(function (PaymentSchedule $s) {
                    $s->update(['deleted_by' => Context::get('user_id')]);
                    $s->delete();
                });
            $tenantId = Context::get('tenant_id') ?? $invoice->tenant_id;
            $userId = Context::get('user_id');
            $created = [];
            foreach ($installments as $inst) {
                $created[] = PaymentSchedule::create([
                    'tenant_id' => $tenantId,
                    'source_document_type' => PaymentSchedule::SOURCE_SAL_INVOICE,
                    'source_document_id' => $salesInvoiceId,
                    'currency_id' => $invoice->currency_id,
                    'due_date' => $inst['due_date'],
                    'expected_amount' => round((float) $inst['amount'], 4),
                    'paid_amount' => 0,
                    'status' => PaymentSchedule::STATUS_PENDING,
                    'created_by' => $userId,
                    'row_version' => 1,
                ]);
            }
            return collect($created);
        });
    }

    public function getSchedule(string $paymentScheduleId): PaymentSchedule
    {
        $schedule = PaymentSchedule::query()->with('cashTransactions')->find($paymentScheduleId);
        if (!$schedule) {
            throw new NotFoundHttpException('Payment schedule not found.');
        }
        return $schedule;
    }

    public function listSchedulesForDocument(string $sourceType, string $sourceDocumentId)
    {
        return PaymentSchedule::query()
            ->where('source_document_type', $sourceType)
            ->where('source_document_id', $sourceDocumentId)
            ->with('cashTransactions')
            ->orderBy('due_date')
            ->get();
    }
}
