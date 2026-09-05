<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\ItemBarcode;
use App\Modules\Inventory\Support\OutboxPublisher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Context;
use Exception;
use Illuminate\Database\Eloquent\Collection;

/**
 * L6-INV-17 — Item barcodes (Owner: Inventory).
 */
class ItemBarcodeService
{
    public function __construct(
        private readonly ItemLookupService $itemLookup,
    ) {
    }

    public function getAll(?string $itemId = null): Collection
    {
        $query = ItemBarcode::query();
        if ($itemId) {
            $query->where('item_id', $itemId);
        }
        return $query->orderByDesc('is_primary')->orderBy('barcode')->get();
    }

    public function getById(string $id): ItemBarcode
    {
        return ItemBarcode::findOrFail($id);
    }

    public function create(array $data): ItemBarcode
    {
        try {
            return DB::transaction(function () use ($data) {
                $tenantId = Context::get('tenant_id');
                $this->itemLookup->requireActive($data['item_id']);

                if (!empty($data['is_primary'])) {
                    $this->clearPrimary($tenantId, $data['item_id']);
                }

                $row = ItemBarcode::create([
                    'tenant_id'    => $tenantId,
                    'item_id'      => $data['item_id'],
                    'barcode'      => $data['barcode'],
                    'barcode_type' => $data['barcode_type'] ?? 'EAN13',
                    'sku'          => $data['sku'] ?? null,
                    'is_primary'   => (bool) ($data['is_primary'] ?? false),
                    'created_by'   => Context::get('user_id'),
                    'row_version'  => 1,
                ]);

                OutboxPublisher::publish(
                    $tenantId,
                    'inv_item_barcodes',
                    $row->barcode_id,
                    'inventory.item_barcode.created.v1',
                    [
                        'barcode_id' => $row->barcode_id,
                        'item_id'    => $row->item_id,
                        'barcode'    => $row->barcode,
                    ]
                );

                return $row;
            });
        } catch (Exception $e) {
            Log::error('Failed to create ItemBarcode: ' . $e->getMessage());
            throw $e;
        }
    }

    public function update(string $id, array $data): ItemBarcode
    {
        try {
            return DB::transaction(function () use ($id, $data) {
                $tenantId = Context::get('tenant_id');
                $row = ItemBarcode::findOrFail($id);

                if (array_key_exists('is_primary', $data) && $data['is_primary']) {
                    $this->clearPrimary($tenantId, $row->item_id, $row->barcode_id);
                }

                $update = array_filter([
                    'barcode'      => $data['barcode'] ?? null,
                    'barcode_type' => $data['barcode_type'] ?? null,
                    'sku'          => $data['sku'] ?? null,
                    'updated_by'   => Context::get('user_id'),
                ], fn ($v) => $v !== null);

                if (array_key_exists('is_primary', $data)) {
                    $update['is_primary'] = (bool) $data['is_primary'];
                }

                $update['row_version'] = ((int) ($row->row_version ?? 1)) + 1;
                $row->update($update);

                OutboxPublisher::publish(
                    $tenantId,
                    'inv_item_barcodes',
                    $row->barcode_id,
                    'inventory.item_barcode.updated.v1',
                    [
                        'barcode_id' => $row->barcode_id,
                        'item_id'    => $row->item_id,
                        'barcode'    => $row->barcode,
                    ]
                );

                return $row->fresh();
            });
        } catch (Exception $e) {
            Log::error('Failed to update ItemBarcode: ' . $e->getMessage());
            throw $e;
        }
    }

    public function delete(string $id): void
    {
        try {
            DB::transaction(function () use ($id) {
                $tenantId = Context::get('tenant_id');
                $row = ItemBarcode::findOrFail($id);
                $row->update(['deleted_by' => Context::get('user_id')]);
                $row->delete();

                OutboxPublisher::publish(
                    $tenantId,
                    'inv_item_barcodes',
                    $row->barcode_id,
                    'inventory.item_barcode.deleted.v1',
                    [
                        'barcode_id' => $row->barcode_id,
                        'item_id'    => $row->item_id,
                        'barcode'    => $row->barcode,
                    ]
                );
            });
        } catch (Exception $e) {
            Log::error('Failed to delete ItemBarcode: ' . $e->getMessage());
            throw $e;
        }
    }

    private function clearPrimary(string $tenantId, string $itemId, ?string $exceptId = null): void
    {
        $q = ItemBarcode::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('item_id', $itemId)
            ->where('is_primary', true)
            ->whereNull('deleted_at');

        if ($exceptId) {
            $q->where('barcode_id', '!=', $exceptId);
        }

        $q->update([
            'is_primary'  => false,
            'updated_at'  => now(),
            'row_version' => DB::raw('row_version + 1'),
        ]);
    }
}
