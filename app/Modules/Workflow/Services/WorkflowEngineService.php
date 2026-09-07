<?php

namespace App\Modules\Workflow\Services;

use App\Modules\Workflow\Models\ProcessDefinition;
use App\Modules\Workflow\Models\ProcessInstance;
use App\Modules\Workflow\Models\Task;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * L6-WF-00/01/02 – Dynamic workflow engine (ADD-04).
 * flow_graph shape (v1):
 * {
 *   "initial_state": "pending_approval",
 *   "states": {
 *     "pending_approval": {
 *       "task_name": "Approve document",
 *       "assigned_type": 1,
 *       "assigned_to_id": "<role_uuid>",
 *       "on_approve": "approved",
 *       "on_reject": "rejected"
 *     },
 *     "approved": { "terminal": true },
 *     "rejected": { "terminal": true }
 *   }
 * }
 */
class WorkflowEngineService
{
    public const INSTANCE_RUNNING = 1;
    public const INSTANCE_COMPLETED = 2;
    public const INSTANCE_TERMINATED = 3;

    public const TASK_PENDING = 1;
    public const TASK_APPROVED = 2;
    public const TASK_REJECTED = 3;

    public const ASSIGN_INTERNAL_ROLE = 1;
    public const ASSIGN_EXTERNAL_BP = 2;

    /**
     * @param  array<string, mixed>  $flowGraph
     */
    public function upsertDefinition(
        string $code,
        string $name,
        string $targetAggregateType,
        array $flowGraph,
        bool $isActive = true
    ): ProcessDefinition {
        $tenantId = Context::get('tenant_id');
        $userId = Context::get('user_id');
        if (!$tenantId) {
            throw new Exception('Tenant Context is missing.');
        }

        if (empty($flowGraph['initial_state']) || empty($flowGraph['states']) || !is_array($flowGraph['states'])) {
            throw new ConflictHttpException('flow_graph must contain initial_state and states map.');
        }

        $existing = ProcessDefinition::query()
            ->where('code', $code)
            ->first();

        if ($existing) {
            $existing->update([
                'name'                  => $name,
                'target_aggregate_type' => $targetAggregateType,
                'flow_graph'            => $flowGraph,
                'is_active'             => $isActive,
                'updated_by'            => $userId,
                'row_version'           => ((int) ($existing->row_version ?? 1)) + 1,
            ]);

            return $existing->fresh();
        }

        return ProcessDefinition::create([
            'tenant_id'             => $tenantId,
            'code'                  => $code,
            'name'                  => $name,
            'target_aggregate_type' => $targetAggregateType,
            'flow_graph'            => $flowGraph,
            'is_active'             => $isActive,
            'created_by'            => $userId,
            'updated_by'            => $userId,
            'row_version'           => 1,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $contextSnapshot
     */
    public function startInstance(
        string $definitionCode,
        string $targetAggregateType,
        string $targetAggregateId,
        ?array $contextSnapshot = null
    ): ProcessInstance {
        try {
            return DB::transaction(function () use (
                $definitionCode,
                $targetAggregateType,
                $targetAggregateId,
                $contextSnapshot
            ) {
                $tenantId = Context::get('tenant_id');
                $userId = Context::get('user_id');
                if (!$tenantId) {
                    throw new Exception('Tenant Context is missing.');
                }

                $definition = ProcessDefinition::query()
                    ->where('code', $definitionCode)
                    ->where('is_active', true)
                    ->first();

                if (!$definition) {
                    throw new NotFoundHttpException("Active process definition [{$definitionCode}] not found.");
                }

                if ($definition->target_aggregate_type !== $targetAggregateType) {
                    throw new ConflictHttpException(
                        "Definition target [{$definition->target_aggregate_type}] does not match [{$targetAggregateType}]."
                    );
                }

                $graph = $definition->flow_graph;
                $initial = $graph['initial_state'] ?? null;
                if (!$initial || empty($graph['states'][$initial])) {
                    throw new ConflictHttpException('Invalid flow_graph: missing initial_state.');
                }

                $instance = ProcessInstance::create([
                    'tenant_id'             => $tenantId,
                    'process_definition_id' => $definition->process_definition_id,
                    'target_aggregate_id'   => $targetAggregateId,
                    'target_aggregate_type' => $targetAggregateType,
                    'current_state'         => $initial,
                    'owning_tenant_id'      => $tenantId,
                    'status'                => self::INSTANCE_RUNNING,
                    'created_by'            => $userId,
                    'updated_by'            => $userId,
                    'row_version'           => 1,
                ]);

                $this->openTaskForState($instance, $definition, $initial, $contextSnapshot);

                return $instance->load('tasks');
            });
        } catch (Exception $e) {
            Log::error('Failed to start workflow instance: ' . $e->getMessage());
            throw $e;
        }
    }

    public function completeTask(string $taskId, bool $approve): ProcessInstance
    {
        try {
            return DB::transaction(function () use ($taskId, $approve) {
                $tenantId = Context::get('tenant_id');
                $userId = Context::get('user_id');
                if (!$tenantId) {
                    throw new Exception('Tenant Context is missing.');
                }

                $task = Task::query()->lockForUpdate()->find($taskId);
                if (!$task) {
                    throw new NotFoundHttpException('Task not found.');
                }
                if ((int) $task->status !== self::TASK_PENDING) {
                    throw new ConflictHttpException('Only pending tasks can be completed.');
                }

                $instance = ProcessInstance::query()->lockForUpdate()->find($task->process_instance_id);
                if (!$instance || (int) $instance->status !== self::INSTANCE_RUNNING) {
                    throw new ConflictHttpException('Process instance is not running.');
                }

                $definition = ProcessDefinition::query()->findOrFail($instance->process_definition_id);
                $graph = $definition->flow_graph;
                $stateConfig = $graph['states'][$instance->current_state] ?? null;
                if (!$stateConfig) {
                    throw new ConflictHttpException('Current state missing from flow_graph.');
                }

                $task->update([
                    'status'      => $approve ? self::TASK_APPROVED : self::TASK_REJECTED,
                    'actioned_at' => now(),
                    'actioned_by' => $userId,
                    'updated_by'  => $userId,
                    'row_version' => ((int) ($task->row_version ?? 1)) + 1,
                ]);

                $nextKey = $approve ? 'on_approve' : 'on_reject';
                $nextState = $stateConfig[$nextKey] ?? null;
                if (!$nextState || empty($graph['states'][$nextState])) {
                    throw new ConflictHttpException("flow_graph missing transition [{$nextKey}] from [{$instance->current_state}].");
                }

                $nextConfig = $graph['states'][$nextState];
                $isTerminal = !empty($nextConfig['terminal']);

                $instance->update([
                    'current_state' => $nextState,
                    'status'        => $isTerminal
                        ? ($approve ? self::INSTANCE_COMPLETED : self::INSTANCE_TERMINATED)
                        : self::INSTANCE_RUNNING,
                    'updated_by'    => $userId,
                    'row_version'   => ((int) ($instance->row_version ?? 1)) + 1,
                ]);

                if (!$isTerminal) {
                    $this->openTaskForState(
                        $instance->fresh(),
                        $definition,
                        $nextState,
                        $task->context_snapshots
                    );
                }

                return $instance->fresh(['tasks']);
            });
        } catch (Exception $e) {
            Log::error('Failed to complete workflow task: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, Task>
     */
    public function listPendingTasks(int $assignedType, string $assignedToId)
    {
        return Task::query()
            ->where('assigned_type', $assignedType)
            ->where('assigned_to_id', $assignedToId)
            ->where('status', self::TASK_PENDING)
            ->orderBy('created_at')
            ->with('instance')
            ->get();
    }

    public function getInstance(string $processInstanceId): ProcessInstance
    {
        $instance = ProcessInstance::with(['tasks', 'definition'])->find($processInstanceId);
        if (!$instance) {
            throw new NotFoundHttpException('Process instance not found.');
        }

        return $instance;
    }

    public function getTask(string $taskId): Task
    {
        $task = Task::with('instance')->find($taskId);
        if (!$task) {
            throw new NotFoundHttpException('Task not found.');
        }

        return $task;
    }

    /**
     * @param  array<string, mixed>|null  $contextSnapshot
     */
    private function openTaskForState(
        ProcessInstance $instance,
        ProcessDefinition $definition,
        string $state,
        ?array $contextSnapshot
    ): ?Task {
        $config = $definition->flow_graph['states'][$state] ?? null;
        if (!$config || !empty($config['terminal'])) {
            return null;
        }

        $assignedType = (int) ($config['assigned_type'] ?? self::ASSIGN_INTERNAL_ROLE);
        $assignedToId = $config['assigned_to_id'] ?? null;
        if (empty($assignedToId)) {
            throw new ConflictHttpException("State [{$state}] requires assigned_to_id in flow_graph.");
        }

        $userId = Context::get('user_id');

        return Task::create([
            'tenant_id'            => $instance->tenant_id,
            'process_instance_id'  => $instance->process_instance_id,
            'assigned_type'        => $assignedType,
            'assigned_to_id'       => $assignedToId,
            'task_name'            => $config['task_name'] ?? $state,
            'status'               => self::TASK_PENDING,
            'context_snapshots'    => $contextSnapshot,
            'created_by'           => $userId,
            'updated_by'           => $userId,
            'row_version'          => 1,
        ]);
    }
}
