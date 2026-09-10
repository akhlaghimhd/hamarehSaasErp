<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\CostLayer;
use App\Modules\Inventory\Models\InventoryDocument;
use App\Modules\Inventory\Models\InventoryDocumentItem;
use App\Modules\Inventory\Models\Item;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * L6-INV-18 — Perpetual inventory valuation.
 * Item.valuation_method: 1 = FIFO, 2 = Moving Average
 */
class ValuationService
{
    public const METHOD_FIFO = 1;
    public const METHOD_MOVING_AVERAGE = 2;

    /**
     * Apply valuation side-effects for a document about to be posted.
     * - Receipt / increase adjustment: open cost layers; keep line unit_cost
     * - Issue / decrease adjustment: resolve unit_cost on lines (if 0 or force), consume layers
     * - Transfer: move layers from → to at original unit costs (quantity only for accounting)
     */
    public function applyForDocument(InventoryDocument $document): void
    {
        $type = (int) $document->document_type;
        $tenantId = $document->tenant_id;

        foreach ($document->items as $line) {
            $item = Item::query()->find($line->item_id);
            if (!$item) {
                throw new ConflictHttpException("Item {$line->item_id} not found for valuation.");
            }
            $method = (int) ($item->valuation_method ?? self::METHOD_FIFO);

            match ($type) {
                InventoryDocumentService::TYPE_RECEIPT => $this->onInbound(
                    $tenantId, $line, $document, $method
                ),
                InventoryDocumentService::TYPE_ISSUE => $this->onOutbound(
                    $tenantId, $line, $method
                ),
                InventoryDocumentService::TYPE_TRANSFER => $this->onTransfer(
                    $tenantId, $line
                ),
                InventoryDocumentService::TYPE_ADJUSTMENT => $this->onAdjustment(
                    $tenantId, $line, $document, $method
                ),
                default => null,
            };
        }
    }

    private function onInbound(
        string $tenantId,
        InventoryDocumentItem $line,
        InventoryDocument $document,
        int $method
    ): void {
        $qty = (float) $line->quantity;
        $unitCost = (float) $line->unit_cost;
        $locationId = $line->to_location_id;

        if ($method === self::METHOD_MOVING_AVERAGE) {
            $this->addMovingAverageLayer($tenantId, $line->item_id, $locationId, $qty, $unitCost, $document, $line);
        } else {
            $this->createLayer($tenantId, $line->item_id, $locationId, $qty, $unitCost, $document, $line);
        }
    }

    private function onOutbound(string $tenantId, InventoryDocumentItem $line, int $method): void
    {
        $qty = (float) $line->quantity;
        $locationId = $line->from_location_id;

        if ($method === self::METHOD_MOVING_AVERAGE) {
            $avg = $this->movingAverageUnitCost($tenantId, $line->item_id, $locationId);
            $line->unit_cost = $avg;
            $line->save();
            $this->consumeLayersProportionally($tenantId, $line->item_id, $locationId, $qty);
        } else {
            $cost = $this->consumeFifo($tenantId, $line->item_id, $locationId, $qty);
            $line->unit_cost = $qty > 0 ? round($cost / $qty, 4) : 0;
            $line->save();
        }
    }

    private function onTransfer(string $tenantId, InventoryDocumentItem $line): void
    {
        $qty = (float) $line->quantity;
        $chunks = $this->consumeFifoDetailed($tenantId, $line->item_id, $line->from_location_id, $qty);

        $totalCost = 0.0;
        foreach ($chunks as $chunk) {
            $this->createLayer(
                $tenantId,
                $line->item_id,
                $line->to_location_id,
                $chunk['qty'],
                $chunk['unit_cost'],
                null,
                $line,
                $chunk['received_at']
            );
            $totalCost += $chunk['qty'] * $chunk['unit_cost'];
        }

        $line->unit_cost = $qty > 0 ? round($totalCost / $qty, 4) : 0;
        $line->save();
    }

    private function onAdjustment(
        string $tenantId,
        InventoryDocumentItem $line,
        InventoryDocument $document,
        int $method
    ): void {
        if (!empty($line->to_location_id) && empty($line->from_location_id)) {
            $this->onInbound($tenantId, $line, $document, $method);
        } elseif (!empty($line->from_location_id) && empty($line->to_location_id)) {
            $this->onOutbound($tenantId, $line, $method);
        }
    }

    private function createLayer(
        string $tenantId,
        string $itemId,
        string $locationId,
        float $qty,
        float $unitCost,
        ?InventoryDocument $document,
        InventoryDocumentItem $line,
        $receivedAt = null
    ): CostLayer {
        return CostLayer::create([
            'tenant_id'               => $tenantId,
            'item_id'                 => $itemId,
            'location_id'             => $locationId,
            'quantity_remaining'      => $qty,
            'unit_cost'               => $unitCost,
            'received_at'             => $receivedAt ?? now(),
            'source_document_id'      => $document?->document_id,
            'source_document_item_id' => $line->document_item_id,
            'created_by'              => Context::get('user_id'),
            'row_version'             => 1,
        ]);
    }

    private function addMovingAverageLayer(
        string $tenantId,
        string $itemId,
        string $locationId,
        float $qty,
        float $unitCost,
        InventoryDocument $document,
        InventoryDocumentItem $line
    ): void {
        $layers = CostLayer::query()
            ->where('item_id', $itemId)
            ->where('location_id', $locationId)
            ->where('quantity_remaining', '>', 0)
            ->lockForUpdate()
            ->get();

        $oldQty = (float) $layers->sum('quantity_remaining');
        $oldValue = 0.0;
        foreach ($layers as $layer) {
            $oldValue += (float) $layer->quantity_remaining * (float) $layer->unit_cost;
        }

        $newQty = $oldQty + $qty;
        $newValue = $oldValue + ($qty * $unitCost);
        $avg = $newQty > 0 ? round($newValue / $newQty, 4) : 0;

        // Collapse to a single MA layer for simplicity and performance
        foreach ($layers as $layer) {
            $layer->update([
                'quantity_remaining' => 0,
                'updated_by'         => Context::get('user_id'),
                'row_version'        => ((int) ($layer->row_version ?? 1)) + 1,
            ]);
            $layer->delete();
        }

        $this->createLayer($tenantId, $itemId, $locationId, $newQty, $avg, $document, $line);
    }

    private function movingAverageUnitCost(string $tenantId, string $itemId, string $locationId): float
    {
        $layers = CostLayer::query()
            ->where('item_id', $itemId)
            ->where('location_id', $locationId)
            ->where('quantity_remaining', '>', 0)
            ->get();

        $qty = (float) $layers->sum('quantity_remaining');
        if ($qty <= 0) {
            throw new ConflictHttpException("No cost layers available for MA issue of item {$itemId}.");
        }

        $value = 0.0;
        foreach ($layers as $layer) {
            $value += (float) $layer->quantity_remaining * (float) $layer->unit_cost;
        }

        return round($value / $qty, 4);
    }

    private function consumeFifo(string $tenantId, string $itemId, string $locationId, float $qty): float
    {
        $chunks = $this->consumeFifoDetailed($tenantId, $itemId, $locationId, $qty);
        $total = 0.0;
        foreach ($chunks as $c) {
            $total += $c['qty'] * $c['unit_cost'];
        }

        return round($total, 4);
    }

    /**
     * @return list<array{qty:float,unit_cost:float,received_at:mixed}>
     */
    private function consumeFifoDetailed(string $tenantId, string $itemId, string $locationId, float $qty): array
    {
        $remaining = $qty;
        $chunks = [];

        // Deterministic FIFO order: oldest received first, then created_at, then PK
        $layers = CostLayer::query()
            ->where('item_id', $itemId)
            ->where('location_id', $locationId)
            ->where('quantity_remaining', '>', 0)
            ->orderBy('received_at')
            ->orderBy('created_at')
            ->orderBy('cost_layer_id')
            ->lockForUpdate()
            ->get();

        foreach ($layers as $layer) {
            if ($remaining <= 1e-9) {
                break;
            }
            $available = (float) $layer->quantity_remaining;
            $take = min($available, $remaining);
            $chunks[] = [
                'qty'         => $take,
                'unit_cost'   => (float) $layer->unit_cost,
                'received_at' => $layer->received_at,
            ];
            $newQty = round($available - $take, 4);
            $layer->update([
                'quantity_remaining' => $newQty,
                'updated_by'         => Context::get('user_id'),
                'row_version'        => ((int) ($layer->row_version ?? 1)) + 1,
            ]);
            if ($newQty <= 1e-9) {
                $layer->delete();
            }
            $remaining = round($remaining - $take, 4);
        }

        if ($remaining > 1e-6) {
            throw new ConflictHttpException(
                "Insufficient cost-layer quantity for item {$itemId} at location {$locationId}. Short by {$remaining}."
            );
        }

        return $chunks;
    }

    private function consumeLayersProportionally(
        string $tenantId,
        string $itemId,
        string $locationId,
        float $qty
    ): void {
        // For MA, all layers share the same unit cost after collapse; FIFO consume is fine
        $this->consumeFifo($tenantId, $itemId, $locationId, $qty);
    }
}
