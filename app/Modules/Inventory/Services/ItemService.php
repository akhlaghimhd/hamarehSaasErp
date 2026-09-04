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
                    'tenant_id' => $tenantId,
                    'code' => $dto->code,
                    'name' => $dto->name,
                    'item_type' => $dto->item_type,
                    'base_uom' => $dto->base_uom,
                    'status' => $dto->status,
                    'created_by' => Context::get('user_id'),
                ]);

                $this->dispatchOutboxEvent('inventory.item.created', $item, $tenantId);

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
                    'name' => $dto->name,
                    'item_type' => $dto->item_type,
                    'base_uom' => $dto->base_uom,
                    'status' => $dto->status,
                    'updated_by' => Context::get('user_id'),
                ], fn($value) => !is_null($value));

                $item->update($updateData);

                $this->dispatchOutboxEvent('inventory.item.updated', $item, $tenantId);

                return $item;
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

                $this->dispatchOutboxEvent('inventory.item.deleted', $item, $tenantId);
            });
        } catch (Exception $e) {
            Log::error('Failed to delete Item: ' . $e->getMessage());
            throw $e;
        }
    }

    private function dispatchOutboxEvent(string $eventType, Item $item, string $tenantId): void
    {
        DB::table('event_outbox')->insert([
            'event_id' => Str::uuid()->toString(),
            'tenant_id' => $tenantId,
            'aggregate_type' => 'items',
            'aggregate_id' => $item->item_id,
            'event_type' => $eventType,
            'payload' => json_encode($item->toArray()),
            'status' => 1, // Pending
            'created_at' => now(),
        ]);
    }
}
