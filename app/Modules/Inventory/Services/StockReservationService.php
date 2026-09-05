<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Support\OutboxPublisher;
use App\Modules\Inventory\Events\StockReservedV1;
use App\Modules\Inventory\Events\StockReservationReleasedV1;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * L6-INV-13 — Soft reservation against inv_stock_balances.quantity_reserved.
 * quantity_available is GENERATED (on_hand - reserved); never written directly.
 * Other modules call this service in-process (logical source_document_* UUIDs only).
 */
class StockReservationService
{
    /**
     * @param  array{source_document_type?: string|null, source_document_id?: string|null}  $meta
     */
    public function reserve(
        string $locationId,
        string $itemId,
        float $quantity,
        array $meta = []
    ): StockBalance {
        if ($quantity <= 0) {
            throw new ConflictHttpException('Reservation quantity must be greater than zero.');
        }

        try {
            return DB::transaction(function () use ($locationId, $itemId, $quantity, $meta) {
                $tenantId = Context::get('tenant_id');
                Location::findOrFail($locationId);

                $balance = StockBalance::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('location_id', $locationId)
                    ->where('item_id', $itemId)
                    ->lockForUpdate()
                    ->first();

                if ($balance === null) {
                    throw new ConflictHttpException(
                        "No stock balance for item {$itemId} at location {$locationId}."
                    );
                }

                $onHand   = (float) $balance->quantity_on_hand;
                $reserved = (float) $balance->quantity_reserved;
                $available = $onHand - $reserved;

                if ($quantity > $available + 1e-9) {
                    throw new ConflictHttpException(
                        "Insufficient available stock for item {$itemId} at location {$locationId}. "
                        . "Available: {$available}, requested: {$quantity}."
                    );
                }

                $balance->update([
                    'quantity_reserved' => $reserved + $quantity,
                    'row_version'       => ((int) ($balance->row_version ?? 1)) + 1,
                    'updated_at'        => now(),
                ]);

                $fresh = $balance->fresh();

                OutboxPublisher::publish(
                    $tenantId,
                    StockReservedV1::AGGREGATE_TYPE,
                    $fresh->stock_balance_id,
                    StockReservedV1::EVENT_TYPE,
                    StockReservedV1::payload($fresh, $quantity, $meta)
                );

                return $fresh;
            });
        } catch (Exception $e) {
            Log::error('Failed to reserve stock: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * @param  array{source_document_type?: string|null, source_document_id?: string|null}  $meta
     */
    public function release(
        string $locationId,
        string $itemId,
        float $quantity,
        array $meta = []
    ): StockBalance {
        if ($quantity <= 0) {
            throw new ConflictHttpException('Release quantity must be greater than zero.');
        }

        try {
            return DB::transaction(function () use ($locationId, $itemId, $quantity, $meta) {
                $tenantId = Context::get('tenant_id');

                $balance = StockBalance::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('location_id', $locationId)
                    ->where('item_id', $itemId)
                    ->lockForUpdate()
                    ->first();

                if ($balance === null) {
                    throw new ConflictHttpException(
                        "No stock balance for item {$itemId} at location {$locationId}."
                    );
                }

                $reserved = (float) $balance->quantity_reserved;
                if ($quantity > $reserved + 1e-9) {
                    throw new ConflictHttpException(
                        "Cannot release more than reserved. Reserved: {$reserved}, requested: {$quantity}."
                    );
                }

                $balance->update([
                    'quantity_reserved' => $reserved - $quantity,
                    'row_version'       => ((int) ($balance->row_version ?? 1)) + 1,
                    'updated_at'        => now(),
                ]);

                $fresh = $balance->fresh();

                OutboxPublisher::publish(
                    $tenantId,
                    StockReservationReleasedV1::AGGREGATE_TYPE,
                    $fresh->stock_balance_id,
                    StockReservationReleasedV1::EVENT_TYPE,
                    StockReservationReleasedV1::payload($fresh, $quantity, $meta)
                );

                return $fresh;
            });
        } catch (Exception $e) {
            Log::error('Failed to release stock reservation: ' . $e->getMessage());
            throw $e;
        }
    }
}
