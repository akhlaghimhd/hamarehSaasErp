<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\StockBalance;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * L6-PS-05 – Inventory side: soft-reserve stock from SalesOrderConfirmedV1.
 * No physical FK; source_document_type / source_document_id in meta only.
 */
class SalesOrderStockReservationService
{
    public const SOURCE_TYPE = 'SAL_ORDER';

    public function __construct(
        private readonly StockReservationService $reservationService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<StockBalance>
     */
    public function reserveFromConfirmedOrder(array $payload): array
    {
        $tenantId = Context::get('tenant_id') ?: ($payload['tenant_id'] ?? null);
        if (!$tenantId) {
            throw new Exception('Tenant Context is missing for SalesOrder → Stock Reservation.');
        }
        Context::add('tenant_id', $tenantId);

        $salesOrderId = $payload['sales_order_id'] ?? null;
        $warehouseId = $payload['warehouse_id'] ?? null;
        $lines = $payload['lines'] ?? [];

        if (empty($salesOrderId)) {
            throw new Exception('sales_order_id is required in SalesOrderConfirmed payload.');
        }
        if (empty($warehouseId)) {
            throw new Exception('warehouse_id is required in SalesOrderConfirmed payload.');
        }
        if (!is_array($lines) || count($lines) < 1) {
            throw new Exception('SalesOrderConfirmed payload must contain at least one line.');
        }

        $locationId = $this->resolveDefaultLocationId($warehouseId);

        return DB::transaction(function () use ($salesOrderId, $locationId, $lines) {
            $results = [];

            foreach ($lines as $line) {
                $itemId = $line['item_id'] ?? null;
                $qty = (float) ($line['quantity'] ?? 0);

                if (empty($itemId) || $qty <= 0) {
                    throw new ConflictHttpException('Invalid line in SalesOrderConfirmed payload.');
                }

                $meta = [
                    'source_document_type' => self::SOURCE_TYPE,
                    'source_document_id'   => $salesOrderId,
                ];

                $balance = $this->reservationService->reserve(
                    $locationId,
                    $itemId,
                    $qty,
                    $meta,
                );

                $results[] = $balance;
            }

            Log::info('Stock reserved for confirmed sales order', [
                'sales_order_id' => $salesOrderId,
                'location_id'    => $locationId,
                'lines'          => count($results),
            ]);

            return $results;
        });
    }

    private function resolveDefaultLocationId(string $warehouseId): string
    {
        $location = Location::query()
            ->where('warehouse_id', $warehouseId)
            ->where('status', 1)
            ->orderBy('code')
            ->first();

        if (!$location) {
            throw new ConflictHttpException(
                'No active location found for warehouse ' . $warehouseId
                . '. Create a location before confirming sales orders.'
            );
        }

        return $location->location_id;
    }
}
