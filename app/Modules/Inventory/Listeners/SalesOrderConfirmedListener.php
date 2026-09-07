<?php

namespace App\Modules\Inventory\Listeners;

use App\Modules\Inventory\Services\SalesOrderStockReservationService;
use App\Modules\ProcurementSales\Events\SalesOrderConfirmedV1;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * L6-PS-05 – Consumes procurement.sales-order.confirmed.v1 from ProcessOutboxMessageJob.
 * Soft-reserves stock via StockReservationService (quantity_reserved).
 */
class SalesOrderConfirmedListener
{
    public function __construct(
        private readonly SalesOrderStockReservationService $reservationService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        try {
            $balances = $this->reservationService->reserveFromConfirmedOrder($payload);

            Log::info('Stock reserved from SalesOrderConfirmedV1', [
                'sales_order_id' => $payload['sales_order_id'] ?? null,
                'reservations'   => count($balances),
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to reserve stock from SalesOrderConfirmedV1: ' . $e->getMessage(), [
                'sales_order_id' => $payload['sales_order_id'] ?? null,
            ]);
            throw $e;
        }
    }
}
