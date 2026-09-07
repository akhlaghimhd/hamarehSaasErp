<?php

namespace App\Modules\Manufacturing\Services;

use App\Modules\Manufacturing\Models\Bom;
use App\Modules\Manufacturing\Models\BomItem;
use App\Modules\Manufacturing\Support\OutboxPublisher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BomService
{
    public const STATUS_DRAFT = 1;
    public const STATUS_APPROVED = 2;
    public const STATUS_OBSOLETE = 3;

    public function list(): Collection
    {
        return Bom::query()->with('items')->orderByDesc('created_at')->get();
    }

    public function getById(string $id): Bom
    {
        $bom = Bom::query()->with('items')->find($id);
        if (!$bom) {
            throw new NotFoundHttpException('BOM not found.');
        }

        return $bom;
    }

    /**
     * @param  array{item_id:string,version_code:string,title:string,is_active?:bool,status?:int,items?:array<int,array{material_item_id:string,quantity:float,scrap_percentage?:float,notes?:string}>}  $data
     */
    public function create(array $data): Bom
    {
        try {
            return DB::transaction(function () use ($data) {
                $tenantId = Context::get('tenant_id');
                $userId = Context::get('user_id');
                if (!$tenantId) {
                    throw new Exception('Tenant Context is missing.');
                }

                $bom = Bom::create([
                    'tenant_id'    => $tenantId,
                    'item_id'      => $data['item_id'],
                    'version_code' => $data['version_code'],
                    'title'        => $data['title'],
                    'is_active'    => $data['is_active'] ?? true,
                    'status'       => $data['status'] ?? self::STATUS_DRAFT,
                    'created_by'   => $userId,
                    'row_version'  => 1,
                ]);

                foreach ($data['items'] ?? [] as $line) {
                    BomItem::create([
                        'tenant_id'         => $tenantId,
                        'bom_id'            => $bom->bom_id,
                        'material_item_id'  => $line['material_item_id'],
                        'quantity'          => $line['quantity'],
                        'scrap_percentage'  => $line['scrap_percentage'] ?? 0,
                        'notes'             => $line['notes'] ?? null,
                        'created_by'        => $userId,
                        'row_version'       => 1,
                    ]);
                }

                OutboxPublisher::publish(
                    $tenantId,
                    'mfg_boms',
                    $bom->bom_id,
                    'manufacturing.bom.created.v1',
                    [
                        'bom_id'       => $bom->bom_id,
                        'item_id'      => $bom->item_id,
                        'version_code' => $bom->version_code,
                        'status'       => $bom->status,
                    ]
                );

                return $bom->fresh(['items']);
            });
        } catch (Exception $e) {
            Log::error('Failed to create BOM: ' . $e->getMessage());
            throw $e;
        }
    }

    public function approve(string $id): Bom
    {
        $bom = Bom::query()->lockForUpdate()->find($id);
        if (!$bom) {
            throw new NotFoundHttpException('BOM not found.');
        }
        if ((int) $bom->status !== self::STATUS_DRAFT) {
            throw new ConflictHttpException('Only draft BOMs can be approved.');
        }
        if ($bom->items()->count() === 0) {
            throw new ConflictHttpException('Cannot approve a BOM with no component lines.');
        }

        $bom->update([
            'status'      => self::STATUS_APPROVED,
            'is_active'   => true,
            'updated_by'  => Context::get('user_id'),
            'row_version' => ((int) ($bom->row_version ?? 1)) + 1,
        ]);

        return $bom->fresh(['items']);
    }

    public function delete(string $id): void
    {
        $bom = Bom::query()->lockForUpdate()->find($id);
        if (!$bom) {
            throw new NotFoundHttpException('BOM not found.');
        }
        if ((int) $bom->status === self::STATUS_APPROVED) {
            throw new ConflictHttpException('Approved BOMs cannot be deleted; mark obsolete instead.');
        }

        $bom->update(['deleted_by' => Context::get('user_id')]);
        $bom->delete();
    }
}
