<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Infrastructure\Listeners;

use App\Modules\FinancialAccounting\Application\Services\OperationalEventConsumer;
use Illuminate\Support\Facades\Log;

class OperationalSalesInvoicePostedListener
{
    public function __construct(
        protected OperationalEventConsumer $consumer = new OperationalEventConsumer()
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload = []): void
    {
        try {
            $payload['_event_type'] = OperationalEventConsumer::SALES_INVOICE_POSTED;
            $payload['event_type'] = OperationalEventConsumer::SALES_INVOICE_POSTED;
            $this->consumer->consume(OperationalEventConsumer::SALES_INVOICE_POSTED, $payload);
        } catch (\Throwable $e) {
            Log::error('finance.listener.sales_invoice_failed', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
