<?php

namespace App\Modules\ProjectManagement\Services;

use App\Modules\ProjectManagement\Models\ProjectTask;
use App\Modules\ProjectManagement\Models\ResourceAllocation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ResourceAllocationService
{
    public function listForTask(string $taskId): Collection
    {
        return ResourceAllocation::query()
            ->where('task_id', $taskId)
            ->orderBy('start_date')
            ->get();
    }

    /**
     * @param  array{resource_type:int,resource_id:string,allocated_quantity?:float,start_date:string,end_date:string}  $data
     */
    public function allocate(string $taskId, array $data): ResourceAllocation
    {
        try {
            return DB::transaction(function () use ($taskId, $data) {
                $tenantId = Context::get('tenant_id');
                $userId = Context::get('user_id');
                if (!$tenantId) {
                    throw new Exception('Tenant Context is missing.');
                }

                $task = ProjectTask::query()->find($taskId);
                if (!$task) {
                    throw new NotFoundHttpException('Task not found.');
                }
                if ((int) $task->status === ProjectTask::STATUS_DONE) {
                    throw new ConflictHttpException('Cannot allocate resources to a completed task.');
                }

                $type = (int) $data['resource_type'];
                if (!in_array($type, [
                    ResourceAllocation::TYPE_HUMAN,
                    ResourceAllocation::TYPE_MACHINE,
                    ResourceAllocation::TYPE_MATERIAL,
                ], true)) {
                    throw new ConflictHttpException('Invalid resource_type.');
                }

                if ($data['end_date'] < $data['start_date']) {
                    throw new ConflictHttpException('end_date must be on or after start_date.');
                }

                return ResourceAllocation::create([
                    'tenant_id'          => $tenantId,
                    'task_id'            => $taskId,
                    'resource_type'      => $type,
                    'resource_id'        => $data['resource_id'],
                    'allocated_quantity' => $data['allocated_quantity'] ?? 1,
                    'start_date'         => $data['start_date'],
                    'end_date'           => $data['end_date'],
                    'created_by'         => $userId,
                    'row_version'        => 1,
                ]);
            });
        } catch (Exception $e) {
            Log::error('Failed to allocate resource: ' . $e->getMessage());
            throw $e;
        }
    }

    public function release(string $allocationId): void
    {
        $row = ResourceAllocation::query()->find($allocationId);
        if (!$row) {
            throw new NotFoundHttpException('Resource allocation not found.');
        }

        $row->update([
            'deleted_by'  => Context::get('user_id'),
            'row_version' => ((int) ($row->row_version ?? 1)) + 1,
        ]);
        $row->delete();
    }
}
