<?php

namespace App\Modules\Manufacturing\Services;

use App\Modules\Manufacturing\Models\Bom;
use App\Modules\Manufacturing\Models\ProductionOrder;
use App\Modules\Manufacturing\Support\OutboxPublisher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProductionOrderService
{
    public const STATUS_CANCELLED = 0;
    public const STATUS_DRAFT = 1;
    public const STATUS_RELEASED = 2;
    public const STATUS_IN_PROGRESS = 3;
    public const STATUS_COMPLETED = 4;

    public function list(): Collection
    {
        return ProductionOrder::query()->orderByDesc('created_at')->get();
    }

    public function getById(string $id): ProductionOrder
    {
        $order = ProductionOrder::query()->find($id);
        if (!$order) {
            throw new NotFoundHttpException('Production order not found.');
        }

        return $order;
    }

    /**
     * @param  array{order_number:string,item_id:string,bom_id:?string,planned_quantity:float,start_date:string,due_date:string}  $data
     */
    public function create(array $data): ProductionOrder
    {
        try {
            return DB::transaction(function () use ($data) {
                $tenantId = Context::get('tenant_id');
                $userId = Context::get('user_id');
                if (!$tenantId) {
                    throw new Exception('Tenant Context is missing.');
                }

                if (!empty($data['bom_id'])) {
                    $bom = Bom::query()->find($data['bom_id']);
                    if (!$bom) {
                        throw new NotFoundHttpException('BOM not found.');
                    }
                    if ((int) $bom->status !== BomService::STATUS_APPROVED) {
                        throw new ConflictHttpException('Production orders require an approved BOM.');
                    }
                }

                $order = ProductionOrder::create([
                    'tenant_id'         => $tenantId,
                    'order_number'      => $data['order_number'],
                    'item_id'           => $data['item_id'],
                    'bom_id'            => $data['bom_id'] ?? null,
                    'planned_quantity'  => $data['planned_quantity'],
                    'produced_quantity' => 0,
                    'start_date'        => $data['start_date'],
                    'due_date'          => $data['due_date'],
                    'status'            => self::STATUS_DRAFT,
                    'created_by'        => $userId,
                    'row_version'       => 1,
                ]);

                OutboxPublisher::publish(
                    $tenantId,
                    'mfg_production_orders',
                    $order->production_order_id,
                    'manufacturing.production_order.created.v1',
                    [
                        'production_order_id' => $order->production_order_id,
                        'order_number'        => $order->order_number,
                        'item_id'             => $order->item_id,
                        'planned_quantity'    => (string) $order->planned_quantity,
                        'status'              => $order->status,
                    ]
                );

                return $order;
            });
        } catch (Exception $e) {
            Log::error('Failed to create ProductionOrder: ' . $e->getMessage());
            throw $e;
        }
    }

    public function release(string $id): ProductionOrder
    {
        try {
            return DB::transaction(function () use ($id) {
                $order = ProductionOrder::query()->lockForUpdate()->find($id);
                if (!$order) {
                    throw new NotFoundHttpException('Production order not found.');
                }
                if ((int) $order->status !== self::STATUS_DRAFT) {
                    throw new ConflictHttpException('Only draft production orders can be released.');
                }
                if (empty($order->bom_id)) {
                    throw new ConflictHttpException('Cannot release a production order without BOM.');
                }

                $bom = Bom::query()->find($order->bom_id);
                if (!$bom || (int) $bom->status !== BomService::STATUS_APPROVED) {
                    throw new ConflictHttpException('BOM must be approved before releasing the production order.');
                }

                $tenantId = Context::get('tenant_id');
                $order->update([
                    'status'      => self::STATUS_RELEASED,
                    'updated_by'  => Context::get('user_id'),
                    'row_version' => ((int) ($order->row_version ?? 1)) + 1,
                ]);

                OutboxPublisher::publish(
                    $tenantId,
                    'mfg_production_orders',
                    $order->production_order_id,
                    'manufacturing.production_order.released.v1',
                    [
                        'production_order_id' => $order->production_order_id,
                        'order_number'        => $order->order_number,
                        'item_id'             => $order->item_id,
                        'bom_id'              => $order->bom_id,
                        'planned_quantity'    => (string) $order->planned_quantity,
                        'status'              => self::STATUS_RELEASED,
                    ]
                );

                return $order->fresh();
            });
        } catch (Exception $e) {
            Log::error('Failed to release ProductionOrder: ' . $e->getMessage());
            throw $e;
        }
    }
}
