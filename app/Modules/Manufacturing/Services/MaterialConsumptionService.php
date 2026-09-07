<?php

namespace App\Modules\Manufacturing\Services;

use App\Modules\Accounting\Services\FiscalPeriodService;
use App\Modules\Inventory\DTOs\CreateInventoryDocumentDTO;
use App\Modules\Inventory\DTOs\CreateInventoryDocumentItemDTO;
use App\Modules\Inventory\Models\InventoryDocument;
use App\Modules\Inventory\Services\InventoryDocumentItemService;
use App\Modules\Inventory\Services\InventoryDocumentService;
use App\Modules\Manufacturing\Models\Bom;
use App\Modules\Manufacturing\Models\BomItem;
use App\Modules\Manufacturing\Models\MaterialConsumption;
use App\Modules\Manufacturing\Models\ProductionOrder;
use App\Modules\Manufacturing\Support\OutboxPublisher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * L6-MFG-02 — Plan and post material consumption for production orders.
 * Inventory Issue is created in-process (Service Contract, no HTTP / no physical FK).
 */
class MaterialConsumptionService
{
    public const STATUS_CANCELLED = 0;
    public const STATUS_REGISTERED = 1;
    public const STATUS_POSTED = 2;

    public const SOURCE_TYPE = 'MFG_PO';

    public function __construct(
        private readonly InventoryDocumentService $documentService,
        private readonly InventoryDocumentItemService $itemService,
        private readonly FiscalPeriodService $fiscalPeriodService,
    ) {
    }

    public function listForOrder(string $productionOrderId): Collection
    {
        return MaterialConsumption::query()
            ->where('production_order_id', $productionOrderId)
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Create registered consumption lines from BOM × planned_quantity (with scrap).
     * Idempotent: if lines already exist, returns them.
     */
    public function planFromBom(string $productionOrderId): Collection
    {
        return DB::transaction(function () use ($productionOrderId) {
            $order = ProductionOrder::query()->lockForUpdate()->find($productionOrderId);
            if (!$order) {
                throw new NotFoundHttpException('Production order not found.');
            }
            if (!in_array((int) $order->status, [
                ProductionOrderService::STATUS_RELEASED,
                ProductionOrderService::STATUS_IN_PROGRESS,
            ], true)) {
                throw new ConflictHttpException('Material planning requires a released or in-progress production order.');
            }
            if (empty($order->bom_id)) {
                throw new ConflictHttpException('Production order has no BOM.');
            }

            $existing = MaterialConsumption::query()
                ->where('production_order_id', $productionOrderId)
                ->get();
            if ($existing->isNotEmpty()) {
                return $existing;
            }

            $bom = Bom::query()->with('items')->find($order->bom_id);
            if (!$bom || $bom->items->isEmpty()) {
                throw new ConflictHttpException('Approved BOM with component lines is required.');
            }

            $tenantId = Context::get('tenant_id');
            $userId = Context::get('user_id');
            $plannedOrderQty = (float) $order->planned_quantity;

            $created = collect();
            foreach ($bom->items as $line) {
                /** @var BomItem $line */
                $scrapFactor = 1 + (((float) $line->scrap_percentage) / 100.0);
                $planned = round(((float) $line->quantity) * $plannedOrderQty * $scrapFactor, 4);

                $row = MaterialConsumption::create([
                    'tenant_id'           => $tenantId,
                    'production_order_id' => $order->production_order_id,
                    'bom_item_id'         => $line->bom_item_id,
                    'material_item_id'    => $line->material_item_id,
                    'planned_quantity'    => $planned,
                    'actual_quantity'     => 0,
                    'consumption_date'    => now(),
                    'status'              => self::STATUS_REGISTERED,
                    'created_by'          => $userId,
                    'row_version'         => 1,
                ]);
                $created->push($row);
            }

            return $created;
        });
    }

    /**
     * Post consumption: set actual (default = planned), create+post Inventory ISSUE from location.
     * Moves production order to In Progress.
     *
     * @param  array<string, float>  $actualByMaterialItemId  optional overrides keyed by material_item_id
     */
    public function issueMaterials(
        string $productionOrderId,
        string $fromLocationId,
        array $actualByMaterialItemId = []
    ): Collection {
        try {
            return DB::transaction(function () use ($productionOrderId, $fromLocationId, $actualByMaterialItemId) {
                $tenantId = Context::get('tenant_id');
                $userId = Context::get('user_id');
                if (!$tenantId) {
                    throw new Exception('Tenant Context is missing.');
                }

                $order = ProductionOrder::query()->lockForUpdate()->find($productionOrderId);
                if (!$order) {
                    throw new NotFoundHttpException('Production order not found.');
                }
                if (!in_array((int) $order->status, [
                    ProductionOrderService::STATUS_RELEASED,
                    ProductionOrderService::STATUS_IN_PROGRESS,
                ], true)) {
                    throw new ConflictHttpException('Can only issue materials for released or in-progress orders.');
                }

                $lines = $this->planFromBom($productionOrderId);
                $pending = $lines->filter(fn (MaterialConsumption $c) => (int) $c->status === self::STATUS_REGISTERED);
                if ($pending->isEmpty()) {
                    throw new ConflictHttpException('No registered consumption lines to issue.');
                }

                // Apply actual quantities
                foreach ($pending as $row) {
                    $actual = $actualByMaterialItemId[$row->material_item_id]
                        ?? (float) $row->planned_quantity;
                    if ($actual <= 0) {
                        throw new ConflictHttpException('Actual consumption quantity must be greater than zero.');
                    }
                    $row->update([
                        'actual_quantity' => $actual,
                        'updated_by'      => $userId,
                        'row_version'     => ((int) ($row->row_version ?? 1)) + 1,
                    ]);
                }

                $pending = MaterialConsumption::query()
                    ->where('production_order_id', $productionOrderId)
                    ->where('status', self::STATUS_REGISTERED)
                    ->get();

                $postingDate = now()->toDateString();
                $fiscalPeriodId = $this->fiscalPeriodService->resolveOpenPeriodIdForDate($postingDate)
                    ?? (string) Str::uuid();

                $docDto = new CreateInventoryDocumentDTO(
                    fiscal_period_id: $fiscalPeriodId,
                    document_type: InventoryDocumentService::TYPE_ISSUE,
                    document_number: 'GI-MFG-' . $order->order_number,
                    posting_date: $postingDate,
                    source_document_type: self::SOURCE_TYPE,
                    source_document_id: $order->production_order_id,
                    description: 'Material issue for production order ' . $order->order_number,
                    status: InventoryDocumentService::STATUS_DRAFT,
                );
                $document = $this->documentService->createDocument($docDto);

                $sort = 1;
                foreach ($pending as $row) {
                    $this->itemService->createItem(new CreateInventoryDocumentItemDTO(
                        document_id: $document->document_id,
                        item_id: $row->material_item_id,
                        quantity: (float) $row->actual_quantity,
                        unit_cost: 0,
                        from_location_id: $fromLocationId,
                        to_location_id: null,
                        batch_number: null,
                        sort_order: $sort++,
                    ));
                }

                $posted = $this->documentService->postDocument($document->document_id);

                foreach ($pending as $row) {
                    $row->update([
                        'inventory_document_id' => $posted->document_id,
                        'status'                => self::STATUS_POSTED,
                        'consumed_by'           => $userId,
                        'consumption_date'      => now(),
                        'updated_by'            => $userId,
                        'row_version'           => ((int) ($row->row_version ?? 1)) + 1,
                    ]);
                }

                if ((int) $order->status === ProductionOrderService::STATUS_RELEASED) {
                    $order->update([
                        'status'      => ProductionOrderService::STATUS_IN_PROGRESS,
                        'updated_by'  => $userId,
                        'row_version' => ((int) ($order->row_version ?? 1)) + 1,
                    ]);
                }

                OutboxPublisher::publish(
                    $tenantId,
                    'mfg_production_orders',
                    $order->production_order_id,
                    'manufacturing.material_consumption.posted.v1',
                    [
                        'production_order_id'   => $order->production_order_id,
                        'inventory_document_id' => $posted->document_id,
                        'line_count'            => $pending->count(),
                    ]
                );

                return MaterialConsumption::query()
                    ->where('production_order_id', $productionOrderId)
                    ->get();
            });
        } catch (Exception $e) {
            Log::error('Failed to issue materials: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Complete production: post Inventory RECEIPT for finished good, set produced qty, status Completed.
     */
    public function completeProduction(
        string $productionOrderId,
        string $toLocationId,
        ?float $producedQuantity = null
    ): ProductionOrder {
        try {
            return DB::transaction(function () use ($productionOrderId, $toLocationId, $producedQuantity) {
                $tenantId = Context::get('tenant_id');
                $userId = Context::get('user_id');
                if (!$tenantId) {
                    throw new Exception('Tenant Context is missing.');
                }

                $order = ProductionOrder::query()->lockForUpdate()->find($productionOrderId);
                if (!$order) {
                    throw new NotFoundHttpException('Production order not found.');
                }
                if (!in_array((int) $order->status, [
                    ProductionOrderService::STATUS_RELEASED,
                    ProductionOrderService::STATUS_IN_PROGRESS,
                ], true)) {
                    throw new ConflictHttpException('Only released or in-progress orders can be completed.');
                }

                $qty = $producedQuantity ?? (float) $order->planned_quantity;
                if ($qty <= 0) {
                    throw new ConflictHttpException('Produced quantity must be greater than zero.');
                }

                $postingDate = now()->toDateString();
                $fiscalPeriodId = $this->fiscalPeriodService->resolveOpenPeriodIdForDate($postingDate)
                    ?? (string) Str::uuid();

                $docDto = new CreateInventoryDocumentDTO(
                    fiscal_period_id: $fiscalPeriodId,
                    document_type: InventoryDocumentService::TYPE_RECEIPT,
                    document_number: 'GR-MFG-' . $order->order_number,
                    posting_date: $postingDate,
                    source_document_type: self::SOURCE_TYPE,
                    source_document_id: $order->production_order_id,
                    description: 'Finished goods receipt for production order ' . $order->order_number,
                    status: InventoryDocumentService::STATUS_DRAFT,
                );
                $document = $this->documentService->createDocument($docDto);

                $this->itemService->createItem(new CreateInventoryDocumentItemDTO(
                    document_id: $document->document_id,
                    item_id: $order->item_id,
                    quantity: $qty,
                    unit_cost: 0,
                    from_location_id: null,
                    to_location_id: $toLocationId,
                    batch_number: null,
                    sort_order: 1,
                ));

                $this->documentService->postDocument($document->document_id);

                $order->update([
                    'produced_quantity' => $qty,
                    'status'            => ProductionOrderService::STATUS_COMPLETED,
                    'updated_by'        => $userId,
                    'row_version'       => ((int) ($order->row_version ?? 1)) + 1,
                ]);

                OutboxPublisher::publish(
                    $tenantId,
                    'mfg_production_orders',
                    $order->production_order_id,
                    'manufacturing.production_order.completed.v1',
                    [
                        'production_order_id'   => $order->production_order_id,
                        'item_id'               => $order->item_id,
                        'produced_quantity'     => (string) $qty,
                        'inventory_document_id' => $document->document_id,
                        'status'                => ProductionOrderService::STATUS_COMPLETED,
                    ]
                );

                return $order->fresh();
            });
        } catch (Exception $e) {
            Log::error('Failed to complete production: ' . $e->getMessage());
            throw $e;
        }
    }
}
