<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\InventoryDocument;
use App\Modules\Inventory\Models\InventoryDocumentItem;
use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Services\StockBatchService;
use App\Modules\Inventory\DTOs\CreateInventoryDocumentDTO;
use App\Modules\Inventory\DTOs\UpdateInventoryDocumentDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use App\Modules\Inventory\Support\OutboxPublisher;
use App\Modules\Inventory\Events\StockMovementPostedV1;
use App\Modules\Inventory\Events\InventoryDocumentPostedV1;
use App\Modules\Inventory\Events\InventoryDocumentVoidedV1;

class InventoryDocumentService
{
    public function __construct(
        private readonly InventoryAccountingService $accounting,
    ) {
    }

    public const STATUS_DRAFT = 1;
    public const STATUS_PENDING = 2;
    public const STATUS_POSTED = 3;
    public const STATUS_VOIDED = 4;

    public const TYPE_RECEIPT = 1;
    public const TYPE_ISSUE = 2;
    public const TYPE_TRANSFER = 3;
    public const TYPE_ADJUSTMENT = 4;

    public function getAllDocuments(?int $documentType = null, ?int $status = null): Collection
    {
        $query = InventoryDocument::query()->with('items');

        if ($documentType !== null) {
            $query->where('document_type', $documentType);
        }
        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->orderByDesc('posting_date')->orderByDesc('created_at')->get();
    }

    public function getDocumentById(string $id): InventoryDocument
    {
        return InventoryDocument::with('items')->findOrFail($id);
    }

    public function createDocument(CreateInventoryDocumentDTO $dto): InventoryDocument
    {
        try {
            return DB::transaction(function () use ($dto) {
                $tenantId = Context::get('tenant_id');

                $status = in_array($dto->status, [self::STATUS_DRAFT, self::STATUS_PENDING], true)
                    ? $dto->status
                    : self::STATUS_DRAFT;

                $document = InventoryDocument::create([
                    'tenant_id'            => $tenantId,
                    'fiscal_period_id'     => $dto->fiscal_period_id,
                    'document_type'        => $dto->document_type,
                    'document_number'      => $dto->document_number,
                    'posting_date'         => $dto->posting_date ?? now(),
                    'source_document_type' => $dto->source_document_type,
                    'source_document_id'   => $dto->source_document_id,
                    'business_partner_id'  => $dto->business_partner_id,
                    'status'               => $status,
                    'description'          => $dto->description,
                    'created_by'           => Context::get('user_id'),
                    'row_version'          => 1,
                ]);

                $this->dispatchOutboxEvent('inventory.document.created.v1', $document, $tenantId);

                return $document->load('items');
            });
        } catch (Exception $e) {
            Log::error('Failed to create InventoryDocument: ' . $e->getMessage());
            throw $e;
        }
    }

    public function updateDocument(string $id, UpdateInventoryDocumentDTO $dto): InventoryDocument
    {
        try {
            return DB::transaction(function () use ($id, $dto) {
                $document = InventoryDocument::findOrFail($id);
                $tenantId = Context::get('tenant_id');

                if ((int) $document->status !== self::STATUS_DRAFT) {
                    throw new ConflictHttpException('Only draft inventory documents can be updated.');
                }

                $updateData = array_filter([
                    'posting_date' => $dto->posting_date,
                    'description'  => $dto->description,
                    'status'       => $dto->status !== null && in_array($dto->status, [self::STATUS_DRAFT, self::STATUS_PENDING], true)
                        ? $dto->status
                        : null,
                    'updated_by'   => Context::get('user_id'),
                ], fn ($value) => !is_null($value));

                if ($dto->clear_business_partner) {
                    $updateData['business_partner_id'] = null;
                } elseif ($dto->business_partner_id !== null) {
                    $updateData['business_partner_id'] = $dto->business_partner_id;
                }

                if ($dto->clear_source) {
                    $updateData['source_document_id'] = null;
                    $updateData['source_document_type'] = null;
                } else {
                    if ($dto->source_document_id !== null) {
                        $updateData['source_document_id'] = $dto->source_document_id;
                    }
                    if ($dto->source_document_type !== null) {
                        $updateData['source_document_type'] = $dto->source_document_type;
                    }
                }

                $updateData['row_version'] = ((int) ($document->row_version ?? 1)) + 1;

                $document->update($updateData);

                $this->dispatchOutboxEvent('inventory.document.updated.v1', $document, $tenantId);

                return $document->fresh(['items']);
            });
        } catch (Exception $e) {
            Log::error('Failed to update InventoryDocument: ' . $e->getMessage());
            throw $e;
        }
    }

    public function deleteDocument(string $id): void
    {
        try {
            DB::transaction(function () use ($id) {
                $document = InventoryDocument::findOrFail($id);
                $tenantId = Context::get('tenant_id');

                if ((int) $document->status !== self::STATUS_DRAFT) {
                    throw new ConflictHttpException('Only draft inventory documents can be deleted.');
                }

                $document->update(['deleted_by' => Context::get('user_id')]);
                $document->delete();

                $this->dispatchOutboxEvent('inventory.document.deleted.v1', $document, $tenantId);
            });
        } catch (Exception $e) {
            Log::error('Failed to delete InventoryDocument: ' . $e->getMessage());
            throw $e;
        }
    }

    public function postDocument(string $id): InventoryDocument
    {
        try {
            return DB::transaction(function () use ($id) {
                $tenantId = Context::get('tenant_id');
                $userId = Context::get('user_id');

                $document = InventoryDocument::with('items')->findOrFail($id);

                if ((int) $document->status !== self::STATUS_DRAFT) {
                    throw new ConflictHttpException('Only draft inventory documents can be posted.');
                }

                if ($document->items->isEmpty()) {
                    throw new ConflictHttpException('Document must have at least one line item before posting.');
                }

                $type = (int) $document->document_type;

                foreach ($document->items as $line) {
                    $this->validateLineForPost($line, $type);
                    $this->applyStockMovement($tenantId, $line, $type);
                }

                $document->update([
                    'status'      => self::STATUS_POSTED,
                    'updated_by'  => $userId,
                    'row_version' => ((int) ($document->row_version ?? 1)) + 1,
                ]);

                $fresh = $document->fresh(['items']);
                $voucherId = $this->accounting->postForDocument($fresh);
                if ($voucherId) {
                    $fresh->update(['accounting_voucher_id' => $voucherId]);
                    $fresh = $fresh->fresh(['items']);
                }

                OutboxPublisher::publish(
                    $tenantId,
                    InventoryDocumentPostedV1::AGGREGATE_TYPE,
                    $fresh->document_id,
                    InventoryDocumentPostedV1::EVENT_TYPE,
                    InventoryDocumentPostedV1::payload($fresh)
                );
                OutboxPublisher::publish(
                    $tenantId,
                    StockMovementPostedV1::AGGREGATE_TYPE,
                    $fresh->document_id,
                    StockMovementPostedV1::EVENT_TYPE,
                    StockMovementPostedV1::payload($fresh)
                );

                return $fresh;
            });
        } catch (Exception $e) {
            Log::error('Failed to post InventoryDocument: ' . $e->getMessage());
            throw $e;
        }
    }

    public function voidDocument(string $id): InventoryDocument
    {
        try {
            return DB::transaction(function () use ($id) {
                $tenantId = Context::get('tenant_id');
                $userId = Context::get('user_id');

                $document = InventoryDocument::with('items')->findOrFail($id);

                if ((int) $document->status !== self::STATUS_POSTED) {
                    throw new ConflictHttpException('Only posted inventory documents can be voided.');
                }

                if ($document->items->isEmpty()) {
                    throw new ConflictHttpException('Posted document has no line items to reverse.');
                }

                $type = (int) $document->document_type;

                foreach ($document->items as $line) {
                    $this->reverseStockMovement($tenantId, $line, $type);
                }

                $reversalId = $this->accounting->reverseForDocument(
                    $document,
                    'Inventory document voided'
                );

                $document->update([
                    'status'      => self::STATUS_VOIDED,
                    'updated_by'  => $userId,
                    'row_version' => ((int) ($document->row_version ?? 1)) + 1,
                ]);

                $fresh = $document->fresh(['items']);
                OutboxPublisher::publish(
                    $tenantId,
                    InventoryDocumentVoidedV1::AGGREGATE_TYPE,
                    $fresh->document_id,
                    InventoryDocumentVoidedV1::EVENT_TYPE,
                    InventoryDocumentVoidedV1::payload($fresh)
                );

                if ($reversalId) {
                    Log::info('Inventory void created reversal voucher', [
                        'document_id' => $document->document_id,
                        'reversal_voucher_id' => $reversalId,
                    ]);
                }

                return $fresh;
            });
        } catch (Exception $e) {
            Log::error('Failed to void InventoryDocument: ' . $e->getMessage());
            throw $e;
        }
    }

    private function validateLineForPost(InventoryDocumentItem $line, int $type): void
    {
        $qty = (float) $line->quantity;
        if ($qty <= 0) {
            throw new ConflictHttpException('Line quantity must be greater than zero.');
        }

        match ($type) {
            self::TYPE_RECEIPT => $this->requireLocation($line->to_location_id, 'to_location_id', 'Receipt'),
            self::TYPE_ISSUE => $this->requireLocation($line->from_location_id, 'from_location_id', 'Issue'),
            self::TYPE_TRANSFER => $this->requireTransferLocations($line),
            self::TYPE_ADJUSTMENT => $this->requireAdjustmentLocation($line),
            default => throw new ConflictHttpException('Unsupported document type for posting.'),
        };
    }

    private function requireLocation(?string $locationId, string $field, string $docLabel): void
    {
        if (empty($locationId)) {
            throw new ConflictHttpException("{$docLabel} lines require {$field}.");
        }
    }

    private function requireTransferLocations(InventoryDocumentItem $line): void
    {
        if (empty($line->from_location_id) || empty($line->to_location_id)) {
            throw new ConflictHttpException('Transfer lines require both from_location_id and to_location_id.');
        }
        if ($line->from_location_id === $line->to_location_id) {
            throw new ConflictHttpException('Transfer from and to locations must be different.');
        }
    }

    private function requireAdjustmentLocation(InventoryDocumentItem $line): void
    {
        if (empty($line->to_location_id) && empty($line->from_location_id)) {
            throw new ConflictHttpException('Adjustment lines require to_location_id (increase) or from_location_id (decrease).');
        }
    }

    private function applyStockMovement(string $tenantId, InventoryDocumentItem $line, int $type): void
    {
        $qty = (float) $line->quantity;

        if ($type === self::TYPE_RECEIPT) {
            $this->adjustBalance($tenantId, $line->to_location_id, $line->item_id, $qty);
            $this->applyBatchDelta($tenantId, $line, $qty);
        } elseif ($type === self::TYPE_ISSUE) {
            $this->adjustBalance($tenantId, $line->from_location_id, $line->item_id, -$qty);
            $this->applyBatchDelta($tenantId, $line, -$qty);
        } elseif ($type === self::TYPE_TRANSFER) {
            $this->adjustBalance($tenantId, $line->from_location_id, $line->item_id, -$qty);
            $this->adjustBalance($tenantId, $line->to_location_id, $line->item_id, $qty);
        } elseif ($type === self::TYPE_ADJUSTMENT) {
            if (!empty($line->to_location_id)) {
                $this->adjustBalance($tenantId, $line->to_location_id, $line->item_id, $qty);
                $this->applyBatchDelta($tenantId, $line, $qty);
            } else {
                $this->adjustBalance($tenantId, $line->from_location_id, $line->item_id, -$qty);
                $this->applyBatchDelta($tenantId, $line, -$qty);
            }
        }
    }

    private function reverseStockMovement(string $tenantId, InventoryDocumentItem $line, int $type): void
    {
        $qty = (float) $line->quantity;

        if ($type === self::TYPE_RECEIPT) {
            $this->adjustBalance($tenantId, $line->to_location_id, $line->item_id, -$qty);
            $this->applyBatchDelta($tenantId, $line, -$qty, allowCreateOnPositive: false);
        } elseif ($type === self::TYPE_ISSUE) {
            $this->adjustBalance($tenantId, $line->from_location_id, $line->item_id, $qty);
            $this->applyBatchDelta($tenantId, $line, $qty, allowCreateOnPositive: false);
        } elseif ($type === self::TYPE_TRANSFER) {
            $this->adjustBalance($tenantId, $line->to_location_id, $line->item_id, -$qty);
            $this->adjustBalance($tenantId, $line->from_location_id, $line->item_id, $qty);
        } elseif ($type === self::TYPE_ADJUSTMENT) {
            if (!empty($line->to_location_id)) {
                $this->adjustBalance($tenantId, $line->to_location_id, $line->item_id, -$qty);
                $this->applyBatchDelta($tenantId, $line, -$qty, allowCreateOnPositive: false);
            } else {
                $this->adjustBalance($tenantId, $line->from_location_id, $line->item_id, $qty);
                $this->applyBatchDelta($tenantId, $line, $qty, allowCreateOnPositive: false);
            }
        }
    }

    private function applyBatchDelta(
        string $tenantId,
        InventoryDocumentItem $line,
        float $delta,
        bool $allowCreateOnPositive = true
    ): void {
        if (empty($line->batch_number)) {
            return;
        }

        app(StockBatchService::class)->adjustRemaining(
            $tenantId,
            $line->item_id,
            $line->batch_number,
            $delta,
            $allowCreateOnPositive
        );
    }

    private function adjustBalance(string $tenantId, string $locationId, string $itemId, float $delta): void
    {
        $location = Location::findOrFail($locationId);

        $balance = StockBalance::withoutGlobalScopes()
            ->where('location_id', $locationId)
            ->where('item_id', $itemId)
            ->lockForUpdate()
            ->first();

        if ($balance === null) {
            if ($delta < 0) {
                throw new ConflictHttpException(
                    "Insufficient stock for item {$itemId} at location {$locationId}."
                );
            }

            StockBalance::create([
                'tenant_id'         => $tenantId,
                'warehouse_id'      => $location->warehouse_id,
                'location_id'       => $locationId,
                'item_id'           => $itemId,
                'quantity_on_hand'  => $delta,
                'quantity_reserved' => 0,
                'row_version'       => 1,
                'updated_at'        => now(),
            ]);

            return;
        }

        $newOnHand = (float) $balance->quantity_on_hand + $delta;
        if ($newOnHand < 0) {
            throw new ConflictHttpException(
                "Insufficient stock for item {$itemId} at location {$locationId}."
            );
        }

        // Cannot reduce on-hand below reserved quantity (L6-INV-13)
        $reserved = (float) $balance->quantity_reserved;
        if ($newOnHand + 1e-9 < $reserved) {
            throw new ConflictHttpException(
                "Cannot reduce stock below reserved quantity for item {$itemId} at location {$locationId}. "
                . "Reserved: {$reserved}."
            );
        }

        $balance->update([
            'quantity_on_hand' => $newOnHand,
            'row_version'      => ((int) ($balance->row_version ?? 1)) + 1,
            'updated_at'       => now(),
        ]);
    }

    private function dispatchOutboxEvent(string $eventType, InventoryDocument $document, string $tenantId): void
    {
        OutboxPublisher::publish(
            $tenantId,
            'inv_documents',
            $document->document_id,
            $eventType,
            [
                'document_id'     => $document->document_id,
                'document_number' => $document->document_number,
                'document_type'   => $document->document_type,
                'status'          => $document->status,
            ]
        );
    }
}
