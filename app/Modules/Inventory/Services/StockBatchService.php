<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\StockBatch;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\DTOs\CreateStockBatchDTO;
use App\Modules\Inventory\DTOs\UpdateStockBatchDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * L6-INV-09 — Stock batch / lot tracking (expiration + QC quarantine)
 * quantity_remaining is also adjusted by InventoryDocumentService on post/void
 * when document lines carry batch_number.
 */
class StockBatchService
{
    public const QC_PENDING = 1;
    public const QC_APPROVED = 2;
    public const QC_QUARANTINED = 3;

    /**
     * @param  array{item_id?: string, qc_status?: int}  $filters
     */
    public function getAllBatches(array $filters = []): Collection
    {
        $query = StockBatch::query();

        if (!empty($filters['item_id'])) {
            $query->where('item_id', $filters['item_id']);
        }

        if (isset($filters['qc_status'])) {
            $query->where('qc_status', (int) $filters['qc_status']);
        }

        return $query
            ->orderBy('item_id')
            ->orderBy('batch_number')
            ->get();
    }

    public function getBatchById(string $id): StockBatch
    {
        return StockBatch::findOrFail($id);
    }

    public function createBatch(CreateStockBatchDTO $dto): StockBatch
    {
        try {
            return DB::transaction(function () use ($dto) {
                $tenantId = Context::get('tenant_id');

                Item::where('item_id', $dto->item_id)->where('tenant_id', $tenantId)->firstOrFail();

                $produced = (float) $dto->quantity_produced;
                $remaining = (float) $dto->quantity_remaining;

                if ($remaining > $produced) {
                    throw ValidationException::withMessages([
                        'quantity_remaining' => ['quantity_remaining cannot exceed quantity_produced.'],
                    ]);
                }

                $batch = StockBatch::create([
                    'tenant_id'          => $tenantId,
                    'item_id'            => $dto->item_id,
                    'batch_number'       => $dto->batch_number,
                    'quantity_produced'  => $produced,
                    'quantity_remaining' => $remaining,
                    'production_date'    => $dto->production_date,
                    'expiration_date'    => $dto->expiration_date,
                    'qc_status'          => $dto->qc_status,
                    'row_version'        => 1,
                ]);

                $this->dispatchOutboxEvent('inventory.stock-batch.created.v1', $batch, $tenantId);

                return $batch;
            });
        } catch (Exception $e) {
            Log::error('Failed to create StockBatch: ' . $e->getMessage());
            throw $e;
        }
    }

    public function updateBatch(string $id, UpdateStockBatchDTO $dto): StockBatch
    {
        try {
            return DB::transaction(function () use ($id, $dto) {
                $batch = StockBatch::findOrFail($id);
                $tenantId = Context::get('tenant_id');

                $updateData = [];

                if ($dto->batch_number !== null) {
                    $updateData['batch_number'] = $dto->batch_number;
                }
                if ($dto->quantity_produced !== null) {
                    $updateData['quantity_produced'] = (float) $dto->quantity_produced;
                }
                if ($dto->quantity_remaining !== null) {
                    $updateData['quantity_remaining'] = (float) $dto->quantity_remaining;
                }
                if ($dto->qc_status !== null) {
                    $updateData['qc_status'] = $dto->qc_status;
                }

                if ($dto->clear_production_date) {
                    $updateData['production_date'] = null;
                } elseif ($dto->production_date !== null) {
                    $updateData['production_date'] = $dto->production_date;
                }

                if ($dto->clear_expiration_date) {
                    $updateData['expiration_date'] = null;
                } elseif ($dto->expiration_date !== null) {
                    $updateData['expiration_date'] = $dto->expiration_date;
                }

                $produced = (float) ($updateData['quantity_produced'] ?? $batch->quantity_produced);
                $remaining = (float) ($updateData['quantity_remaining'] ?? $batch->quantity_remaining);
                if ($remaining > $produced) {
                    throw ValidationException::withMessages([
                        'quantity_remaining' => ['quantity_remaining cannot exceed quantity_produced.'],
                    ]);
                }

                $updateData['row_version'] = ((int) ($batch->row_version ?? 1)) + 1;

                $batch->update($updateData);

                $this->dispatchOutboxEvent('inventory.stock-batch.updated.v1', $batch, $tenantId);

                return $batch->fresh();
            });
        } catch (Exception $e) {
            Log::error('Failed to update StockBatch: ' . $e->getMessage());
            throw $e;
        }
    }

    public function deleteBatch(string $id): void
    {
        try {
            DB::transaction(function () use ($id) {
                $batch = StockBatch::findOrFail($id);
                $tenantId = Context::get('tenant_id');

                $batch->update(['deleted_by' => Context::get('user_id')]);
                $batch->delete();

                $this->dispatchOutboxEvent('inventory.stock-batch.deleted.v1', $batch, $tenantId);
            });
        } catch (Exception $e) {
            Log::error('Failed to delete StockBatch: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Called from InventoryDocumentService on post/void when line has batch_number.
     * Positive delta = inbound (receipt / reverse of issue).
     * Negative delta = outbound (issue / reverse of receipt).
     * Quarantined batches (qc_status=3) cannot be issued from.
     */
    public function adjustRemaining(
        string $tenantId,
        string $itemId,
        string $batchNumber,
        float $delta,
        bool $allowCreateOnPositive = true
    ): StockBatch {
        $batch = StockBatch::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('item_id', $itemId)
            ->where('batch_number', $batchNumber)
            ->whereNull('deleted_at')
            ->lockForUpdate()
            ->first();

        if ($batch === null) {
            if ($delta < 0 || !$allowCreateOnPositive) {
                throw new ConflictHttpException(
                    "Stock batch '{$batchNumber}' not found for item {$itemId}."
                );
            }

            $batch = StockBatch::create([
                'tenant_id'          => $tenantId,
                'item_id'            => $itemId,
                'batch_number'       => $batchNumber,
                'quantity_produced'  => $delta,
                'quantity_remaining' => $delta,
                'qc_status'          => self::QC_PENDING,
                'row_version'        => 1,
            ]);

            return $batch;
        }

        if ($delta < 0 && (int) $batch->qc_status === self::QC_QUARANTINED) {
            throw new ConflictHttpException(
                "Stock batch '{$batchNumber}' is quarantined and cannot be issued."
            );
        }

        $newRemaining = (float) $batch->quantity_remaining + $delta;
        if ($newRemaining < 0) {
            throw new ConflictHttpException(
                "Insufficient quantity remaining on batch '{$batchNumber}' for item {$itemId}."
            );
        }

        $newProduced = (float) $batch->quantity_produced;
        if ($delta > 0) {
            $newProduced = max($newProduced, $newRemaining);
        }

        $batch->update([
            'quantity_remaining' => $newRemaining,
            'quantity_produced'  => $newProduced,
            'row_version'        => ((int) ($batch->row_version ?? 1)) + 1,
            'updated_at'         => now(),
        ]);

        return $batch->fresh();
    }

    private function dispatchOutboxEvent(string $eventType, StockBatch $batch, string $tenantId): void
    {
        DB::table('event_outbox')->insert([
            'event_id'       => Str::uuid()->toString(),
            'tenant_id'      => $tenantId,
            'aggregate_type' => 'inv_stock_batches',
            'aggregate_id'   => $batch->batch_id,
            'event_type'     => $eventType,
            'payload'        => json_encode($batch->toArray()),
            'status'         => 1,
            'created_at'     => now(),
        ]);
    }
}
