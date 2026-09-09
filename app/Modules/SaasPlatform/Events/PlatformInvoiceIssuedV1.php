<?php

namespace App\Modules\SaasPlatform\Events;

use App\Modules\SaasPlatform\Models\PlatformInvoice;

/**
 * Domain event — platform invoice issued.
 * Event type string: saas.platform_invoice.issued.v1
 */
final class PlatformInvoiceIssuedV1
{
    public const EVENT_TYPE = 'saas.platform_invoice.issued.v1';
    public const AGGREGATE_TYPE = 'platform_invoices';

    /**
     * @return array<string, mixed>
     */
    public static function payload(PlatformInvoice $invoice): array
    {
        return [
            'event'          => self::EVENT_TYPE,
            'tenant_id'      => $invoice->tenant_id,
            'invoice_id'     => $invoice->invoice_id,
            'invoice_number' => $invoice->invoice_number,
            'final_amount'   => (string) $invoice->final_amount,
            'status'         => (int) $invoice->status,
            'issue_date'     => $invoice->issue_date?->toIso8601String(),
        ];
    }
}
