<?php

namespace App\Modules\SaasPlatform\Services;

use App\Modules\SaasPlatform\Models\PlatformInvoice;
use App\Modules\SaasPlatform\Models\PlatformInvoiceItem;
use App\Modules\SaasPlatform\Models\PlatformTransaction;
use App\Modules\SaasPlatform\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use InvalidArgumentException;

class InvoiceService
{
    public const STATUS_DRAFT = 1;
    public const STATUS_ISSUED = 2;
    public const STATUS_PAID = 3;
    public const STATUS_CANCELLED = 4;

    /**
     * Create a platform invoice with items.
     */
    public function createInvoice(
        string $tenantId,
        array $items, // [['item_type' => '', 'reference_id' => null, 'description' => '', 'amount' => 0.0], ...]
        float $discountAmount = 0,
        float $taxAmount = 0,
        ?Carbon $dueDate = null,
        ?string $createdBy = null
    ): PlatformInvoice {
        if (empty($items)) {
            throw new InvalidArgumentException('Invoice must have at least one item.');
        }

        return DB::transaction(function () use ($tenantId, $items, $discountAmount, $taxAmount, $dueDate, $createdBy) {
            Tenant::where('tenant_id', $tenantId)->whereNull('deleted_at')->firstOrFail();

            $totalAmount = collect($items)->sum('amount');
            $finalAmount = $totalAmount - $discountAmount + $taxAmount;

            $invoiceNumber = 'INV-' . strtoupper(Str::random(8)) . '-' . now()->format('Ymd');

            $invoice = PlatformInvoice::create([
                'tenant_id'        => $tenantId,
                'invoice_number'   => $invoiceNumber,
                'total_amount'     => $totalAmount,
                'discount_amount'  => $discountAmount,
                'tax_amount'       => $taxAmount,
                'final_amount'     => $finalAmount,
                'status'           => self::STATUS_ISSUED,
                'issue_date'       => Carbon::now(),
                'due_date'         => $dueDate,
                'created_by'       => $createdBy,
                'updated_by'       => $createdBy,
            ]);

            foreach ($items as $item) {
                PlatformInvoiceItem::create([
                    'invoice_id'   => $invoice->invoice_id,
                    'item_type'    => $item['item_type'],
                    'reference_id' => $item['reference_id'] ?? null,
                    'description'  => $item['description'] ?? null,
                    'amount'       => $item['amount'],
                    'created_by'   => $createdBy,
                    'updated_by'   => $createdBy,
                ]);
            }

            $this->logEventOutbox(
                $tenantId,
                'platform_invoices',
                $invoice->invoice_id,
                'SaasPlatform.InvoiceCreated.v1',
                [
                    'invoice_id'     => $invoice->invoice_id,
                    'tenant_id'      => $tenantId,
                    'invoice_number' => $invoiceNumber,
                    'final_amount'   => $finalAmount,
                    'status'         => self::STATUS_ISSUED,
                ]
            );

            return $invoice->load('items');
        });
    }

    /**
     * Record a payment transaction against an invoice.
     */
    public function recordPayment(
        string $invoiceId,
        float $amount,
        ?string $gateway = null,
        ?string $transactionNumber = null,
        ?string $createdBy = null
    ): PlatformTransaction {
        return DB::transaction(function () use ($invoiceId, $amount, $gateway, $transactionNumber, $createdBy) {
            $invoice = PlatformInvoice::where('invoice_id', $invoiceId)->whereNull('deleted_at')->firstOrFail();

            $transaction = PlatformTransaction::create([
                'invoice_id'         => $invoiceId,
                'gateway'            => $gateway,
                'transaction_number' => $transactionNumber,
                'amount'             => $amount,
                'status'             => 1, // Success
                'transaction_date'   => Carbon::now(),
                'created_by'         => $createdBy,
                'updated_by'         => $createdBy,
            ]);

            // Simple mark as paid if amount covers final_amount (can be improved later)
            if ($amount >= $invoice->final_amount) {
                $invoice->status = self::STATUS_PAID;
                $invoice->updated_by = $createdBy;
                $invoice->save();

                $this->logEventOutbox(
                    $invoice->tenant_id,
                    'platform_invoices',
                    $invoiceId,
                    'SaasPlatform.InvoicePaid.v1',
                    [
                        'invoice_id'     => $invoiceId,
                        'tenant_id'      => $invoice->tenant_id,
                        'transaction_id' => $transaction->transaction_id,
                        'amount'         => $amount,
                        'status'         => self::STATUS_PAID,
                    ]
                );
            }

            return $transaction;
        });
    }

    /**
     * Write integration event to shared outbox (same pattern as RoleService / SubscriptionService).
     */
    private function logEventOutbox(
        string $tenantId,
        string $aggregateType,
        string $aggregateId,
        string $eventType,
        array $payload
    ): void {
        DB::table('event_outbox')->insert([
            'event_id'       => Str::uuid()->toString(),
            'tenant_id'      => $tenantId,
            'aggregate_type' => $aggregateType,
            'aggregate_id'   => $aggregateId,
            'event_type'     => $eventType,
            'payload'        => json_encode($payload),
            'status'         => 1,
            'created_at'     => now(),
        ]);
    }
}