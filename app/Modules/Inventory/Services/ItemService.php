<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\DTOs\CreateItemDTO;
use App\Modules\Inventory\DTOs\UpdateItemDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Exception;
use Illuminate\Database\Eloquent\Collection;

class ItemService
{
    public function getAllItems(): Collection
    {
        return Item::all();
    }

    public function getItemById(string $id): Item
    {
        return Item::findOrFail($id);
    }

    public function createItem(CreateItemDTO $dto): Item
    {
        try {
            return DB::transaction(function () use ($dto) {
                $tenantId = Context::get('tenant_id');

                $item = Item::create([
                    'tenant_id'         => $tenantId,
                    'item_group_id'     => $dto->item_group_id,
                    'uom_id'            => $dto->uom_id,
                    'code'              => $dto->code,
                    'name'              => $dto->name,
                    'description'       => $dto->description,
                    'item_type'         => $dto->item_type,
                    'valuation_method'  => $dto->valuation_method,
                    'extra_attributes'  => $dto->extra_attributes,
                    'status'            => $dto->status,
                    'created_by'        => Context::get('user_id'),
                    'row_version'       => 1,
                ]);

                $this->dispatchOutboxEvent('inventory.item.created.v1', $item, $tenantId);

                return $item;
            });
        } catch (Exception $e) {
            Log::error('Failed to create Item: ' . $e->getMessage());
            throw $e;
        }
    }

    public function updateItem(string $id, UpdateItemDTO $dto): Item
    {
        try {
            return DB::transaction(function () use ($id, $dto) {
                $item = Item::findOrFail($id);
                $tenantId = Context::get('tenant_id');

                $updateData = array_filter([
                    'name'              => $dto->name,
                    'description'       => $dto->description,
                    'item_type'         => $dto->item_type,
                    'valuation_method'  => $dto->valuation_method,
                    'item_group_id'     => $dto->item_group_id,
                    'uom_id'            => $dto->uom_id,
                    'status'            => $dto->status,
                    'updated_by'        => Context::get('user_id'),
                ], fn ($value) => !is_null($value));

                if ($dto->extra_attributes !== null) {
                    $updateData['extra_attributes'] = $dto->extra_attributes;
                }

                $updateData['row_version'] = ((int) ($item->row_version ?? 1)) + 1;

                $item->update($updateData);

                $this->dispatchOutboxEvent('inventory.item.updated.v1', $item, $tenantId);

                return $item->fresh();
            });
        } catch (Exception $e) {
            Log::error('Failed to update Item: ' . $e->getMessage());
            throw $e;
        }
    }

    public function deleteItem(string $id): void
    {
        try {
            DB::transaction(function () use ($id) {
                $item = Item::findOrFail($id);
                $tenantId = Context::get('tenant_id');

                $item->update(['deleted_by' => Context::get('user_id')]);
                $item->delete();

                $this->dispatchOutboxEvent('inventory.item.deleted.v1', $item, $tenantId);
            });
        } catch (Exception $e) {
            Log::error('Failed to delete Item: ' . $e->getMessage());
            throw $e;
        }
    }

    private function dispatchOutboxEvent(string $eventType, Item $item, string $tenantId): void
    {
        DB::table('event_outbox')->insert([
            'event_id'       => Str::uuid()->toString(),
            'tenant_id'      => $tenantId,
            'aggregate_type' => 'inv_items',
            'aggregate_id'   => $item->item_id,
            'event_type'     => $eventType,
            'payload'        => json_encode($item->toArray()),
            'status'         => 1,
            'created_at'     => now(),
        ]);
    }
}
