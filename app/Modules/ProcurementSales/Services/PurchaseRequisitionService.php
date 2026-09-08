<?php

namespace App\Modules\ProcurementSales\Services;

use App\Modules\ProcurementSales\DTOs\CreatePurchaseRequisitionDTO;
use App\Modules\ProcurementSales\Models\PurchaseRequisition;
use App\Modules\ProcurementSales\Models\PurchaseRequisitionItem;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * L6-PS-09 – Internal purchase requisitions (Draft → Pending → Approved/Rejected).
 */
class PurchaseRequisitionService
{
    public const STATUS_DRAFT = 1;
    public const STATUS_PENDING = 2;
    public const STATUS_APPROVED = 3;
    public const STATUS_REJECTED = 4;

    public const PRIORITY_HIGH = 1;
    public const PRIORITY_MEDIUM = 2;
    public const PRIORITY_LOW = 3;

    public function create(CreatePurchaseRequisitionDTO $dto): PurchaseRequisition
    {
        return DB::transaction(function () use ($dto) {
            $tenantId = Context::get('tenant_id');
            $userId = Context::get('user_id');
            if (!$tenantId) {
                throw new \RuntimeException('Tenant Context is missing.');
            }
            if (empty($dto->items)) {
                throw new ConflictHttpException('Requisition must have at least one line.');
            }

            $number = 'PR-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));

            $req = PurchaseRequisition::create([
                'tenant_id' => $tenantId,
                'department_id' => $dto->departmentId,
                'requisition_number' => $number,
                'required_date' => $dto->requiredDate,
                'priority' => $dto->priority,
                'status' => self::STATUS_DRAFT,
                'description' => $dto->description,
                'created_by' => $userId,
                'updated_by' => $userId,
                'row_version' => 1,
            ]);

            $line = 1;
            foreach ($dto->items as $item) {
                PurchaseRequisitionItem::create([
                    'tenant_id' => $tenantId,
                    'requisition_id' => $req->requisition_id,
                    'item_id' => $item->itemId,
                    'quantity' => $item->quantity,
                    'estimated_unit_price' => $item->estimatedUnitPrice,
                    'uom_code' => $item->uomCode,
                    'line_number' => $item->lineNumber ?: $line,
                    'description' => $item->description,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                    'row_version' => 1,
                ]);
                $line++;
            }

            return $req->load('items');
        });
    }

    public function getById(string $id): PurchaseRequisition
    {
        $req = PurchaseRequisition::query()->with('items')->find($id);
        if (!$req) {
            throw new NotFoundHttpException('Purchase requisition not found.');
        }
        return $req;
    }

    public function submit(string $id): PurchaseRequisition
    {
        return DB::transaction(function () use ($id) {
            $req = PurchaseRequisition::query()->lockForUpdate()->with('items')->find($id);
            if (!$req) {
                throw new NotFoundHttpException('Purchase requisition not found.');
            }
            if ((int) $req->status !== self::STATUS_DRAFT) {
                throw new ConflictHttpException('Only draft requisitions can be submitted.');
            }
            if ($req->items->isEmpty()) {
                throw new ConflictHttpException('Cannot submit a requisition with no lines.');
            }
            $req->update([
                'status' => self::STATUS_PENDING,
                'updated_by' => Context::get('user_id'),
                'row_version' => ((int) ($req->row_version ?? 1)) + 1,
            ]);
            Log::info('PurchaseRequisition submitted', ['requisition_id' => $id]);
            return $req->fresh(['items']);
        });
    }

    public function approve(string $id): PurchaseRequisition
    {
        return DB::transaction(function () use ($id) {
            $req = PurchaseRequisition::query()->lockForUpdate()->find($id);
            if (!$req) {
                throw new NotFoundHttpException('Purchase requisition not found.');
            }
            if ((int) $req->status !== self::STATUS_PENDING) {
                throw new ConflictHttpException('Only pending requisitions can be approved.');
            }
            $req->update([
                'status' => self::STATUS_APPROVED,
                'updated_by' => Context::get('user_id'),
                'row_version' => ((int) ($req->row_version ?? 1)) + 1,
            ]);
            return $req->fresh(['items']);
        });
    }

    public function reject(string $id): PurchaseRequisition
    {
        return DB::transaction(function () use ($id) {
            $req = PurchaseRequisition::query()->lockForUpdate()->find($id);
            if (!$req) {
                throw new NotFoundHttpException('Purchase requisition not found.');
            }
            if ((int) $req->status !== self::STATUS_PENDING) {
                throw new ConflictHttpException('Only pending requisitions can be rejected.');
            }
            $req->update([
                'status' => self::STATUS_REJECTED,
                'updated_by' => Context::get('user_id'),
                'row_version' => ((int) ($req->row_version ?? 1)) + 1,
            ]);
            return $req->fresh(['items']);
        });
    }
}
