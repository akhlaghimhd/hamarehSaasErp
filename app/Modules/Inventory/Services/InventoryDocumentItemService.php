<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\InventoryDocument;
use App\Modules\Inventory\Models\InventoryDocumentItem;
use App\Modules\Inventory\DTOs\CreateInventoryDocumentItemDTO;
use App\Modules\Inventory\DTOs\UpdateInventoryDocumentItemDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class InventoryDocumentItemService
{
    public function __construct(
        private readonly ItemLookupService $itemLookup,
    ) {
    }

    public function getAllItems(?string $documentId = null): Collection
    {
        $query = InventoryDocumentItem::query();

        if ($documentId) {
            $query->where('document_id', $documentId);
        }

        return $query->orderBy('sort_order')->orderBy('created_at')->get();
    }

    public function getItemById(string $id): InventoryDocumentItem
    {
        return InventoryDocumentItem::findOrFail($id);
    }

    public function createItem(CreateInventoryDocumentItemDTO $dto): InventoryDocumentItem
    {
        try {
            return DB::transaction(function () use ($dto) {
                $tenantId = Context::get('tenant_id');
                $document = InventoryDocument::findOrFail($dto->document_id);

                if ((int) $document->status !== InventoryDocumentService::STATUS_DRAFT) {
                    throw new ConflictHttpException('Items can only be added to draft inventory documents.');
                }

                $this->itemLookup->requireActive($dto->item_id);

                $line = InventoryDocumentItem::create([
                    'tenant_id'        => $tenantId,
                    'document_id'      => $dto->document_id,
                    'item_id'          => $dto->item_id,
                    'from_location_id' => $dto->from_location_id,
                    'to_location_id'   => $dto->to_location_id,
                    'batch_number'     => $dto->batch_number,
                    'quantity'         => $dto->quantity,
                    'unit_cost'        => $dto->unit_cost,
                    'sort_order'       => $dto->sort_order,
                    'row_version'      => 1,
                ]);

                $this->dispatchOutboxEvent('inventory.document_item.created.v1', $line, $tenantId);

                return $line->fresh();
            });
        } catch (Exception $e) {
            Log::error('Failed to create InventoryDocumentItem: ' . $e->getMessage());
            throw $e;
        }
    }

    public function updateItem(string $id, UpdateInventoryDocumentItemDTO $dto): InventoryDocumentItem
    {
        try {
            return DB::transaction(function () use ($id, $dto) {
                $line = InventoryDocumentItem::findOrFail($id);
                $tenantId = Context::get('tenant_id');
                $document = InventoryDocument::findOrFail($line->document_id);

                if ((int) $document->status !== InventoryDocumentService::STATUS_DRAFT) {
                    throw new ConflictHttpException('Items can only be updated on draft inventory documents.');
                }

                $updateData = array_filter([
                    'quantity'   => $dto->quantity,
                    'unit_cost'  => $dto->unit_cost,
                    'sort_order' => $dto->sort_order,
                ], fn ($value) => !is_null($value));

                if ($dto->clear_from_location) {
                    $updateData['from_location_id'] = null;
                } elseif ($dto->from_location_id !== null) {
                    $updateData['from_location_id'] = $dto->from_location_id;
                }

                if ($dto->clear_to_location) {
                    $updateData['to_location_id'] = null;
                } elseif ($dto->to_location_id !== null) {
                    $updateData['to_location_id'] = $dto->to_location_id;
                }

                if ($dto->clear_batch_number) {
                    $updateData['batch_number'] = null;
                } elseif ($dto->batch_number !== null) {
                    $updateData['batch_number'] = $dto->batch_number;
                }

                $updateData['row_version'] = ((int) ($line->row_version ?? 1)) + 1;

                $line->update($updateData);

                $this->dispatchOutboxEvent('inventory.document_item.updated.v1', $line, $tenantId);

                return $line->fresh();
            });
        } catch (Exception $e) {
            Log::error('Failed to update InventoryDocumentItem: ' . $e->getMessage());
            throw $e;
        }
    }

    public function deleteItem(string $id): void
    {
        try {
            DB::transaction(function () use ($id) {
                $line = InventoryDocumentItem::findOrFail($id);
                $tenantId = Context::get('tenant_id');
                $document = InventoryDocument::findOrFail($line->document_id);

                if ((int) $document->status !== InventoryDocumentService::STATUS_DRAFT) {
                    throw new ConflictHttpException('Items can only be deleted from draft inventory documents.');
                }

                $this->dispatchOutboxEvent('inventory.document_item.deleted.v1', $line, $tenantId);

                // Hard delete — no soft deletes on line items (per architecture doc)
                $line->delete();
            });
        } catch (Exception $e) {
            Log::error('Failed to delete InventoryDocumentItem: ' . $e->getMessage());
            throw $e;
        }
    }

    private function dispatchOutboxEvent(string $eventType, InventoryDocumentItem $line, string $tenantId): void
    {
        DB::table('event_outbox')->insert([
            'event_id'       => Str::uuid()->toString(),
            'tenant_id'      => $tenantId,
            'aggregate_type' => 'inv_document_items',
            'aggregate_id'   => $line->document_item_id,
            'event_type'     => $eventType,
            'payload'        => json_encode([
                'document_item_id' => $line->document_item_id,
                'document_id'      => $line->document_id,
                'item_id'          => $line->item_id,
                'quantity'         => $line->quantity,
            ]),
            'status'         => 1,
            'created_at'     => now(),
        ]);
    }
}
