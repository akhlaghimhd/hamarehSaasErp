<?php

namespace App\Modules\Manufacturing\Services;

use App\Modules\Manufacturing\Models\WorkCenter;
use App\Modules\Manufacturing\Support\OutboxPublisher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class WorkCenterService
{
    public const STATUS_ACTIVE = 1;
    public const STATUS_INACTIVE = 2;
    public const STATUS_MAINTENANCE = 3;

    public function list(): Collection
    {
        return WorkCenter::query()->orderBy('code')->get();
    }

    public function getById(string $id): WorkCenter
    {
        $wc = WorkCenter::query()->find($id);
        if (!$wc) {
            throw new NotFoundHttpException('Work center not found.');
        }

        return $wc;
    }

    public function create(array $data): WorkCenter
    {
        try {
            return DB::transaction(function () use ($data) {
                $tenantId = Context::get('tenant_id');
                $userId = Context::get('user_id');
                if (!$tenantId) {
                    throw new Exception('Tenant Context is missing.');
                }

                $wc = WorkCenter::create([
                    'tenant_id'              => $tenantId,
                    'code'                   => $data['code'],
                    'name'                   => $data['name'],
                    'capacity_hours_per_day' => $data['capacity_hours_per_day'] ?? 8,
                    'efficiency_percentage'  => $data['efficiency_percentage'] ?? 100,
                    'cost_per_hour'          => $data['cost_per_hour'] ?? 0,
                    'status'                 => $data['status'] ?? self::STATUS_ACTIVE,
                    'created_by'             => $userId,
                    'row_version'            => 1,
                ]);

                OutboxPublisher::publish(
                    $tenantId,
                    'mfg_work_centers',
                    $wc->work_center_id,
                    'manufacturing.work_center.created.v1',
                    [
                        'work_center_id' => $wc->work_center_id,
                        'code'           => $wc->code,
                        'status'         => $wc->status,
                    ]
                );

                return $wc;
            });
        } catch (Exception $e) {
            Log::error('Failed to create WorkCenter: ' . $e->getMessage());
            throw $e;
        }
    }

    public function update(string $id, array $data): WorkCenter
    {
        try {
            return DB::transaction(function () use ($id, $data) {
                $wc = WorkCenter::query()->lockForUpdate()->find($id);
                if (!$wc) {
                    throw new NotFoundHttpException('Work center not found.');
                }

                $payload = array_filter([
                    'code'                   => $data['code'] ?? null,
                    'name'                   => $data['name'] ?? null,
                    'capacity_hours_per_day' => $data['capacity_hours_per_day'] ?? null,
                    'efficiency_percentage'  => $data['efficiency_percentage'] ?? null,
                    'cost_per_hour'          => $data['cost_per_hour'] ?? null,
                    'status'                 => $data['status'] ?? null,
                    'updated_by'             => Context::get('user_id'),
                ], fn ($v) => $v !== null);

                $payload['row_version'] = ((int) ($wc->row_version ?? 1)) + 1;
                $wc->update($payload);

                return $wc->fresh();
            });
        } catch (Exception $e) {
            Log::error('Failed to update WorkCenter: ' . $e->getMessage());
            throw $e;
        }
    }

    public function delete(string $id): void
    {
        try {
            DB::transaction(function () use ($id) {
                $wc = WorkCenter::query()->lockForUpdate()->find($id);
                if (!$wc) {
                    throw new NotFoundHttpException('Work center not found.');
                }

                $wc->update(['deleted_by' => Context::get('user_id')]);
                $wc->delete();
            });
        } catch (Exception $e) {
            Log::error('Failed to delete WorkCenter: ' . $e->getMessage());
            throw $e;
        }
    }
}
