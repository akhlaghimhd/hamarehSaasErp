<?php

namespace App\Modules\Workflow\Events;

/**
 * L6-WF-03 – Boundary event when a worklist task is approved or rejected.
 * Business modules may listen to advance their aggregate (e.g. confirm SO).
 */
final class WorkflowTaskCompletedV1
{
    public const EVENT_TYPE = 'workflow.task.completed.v1';

    public function __construct(
        public readonly string $tenantId,
        public readonly string $taskId,
        public readonly string $processInstanceId,
        public readonly string $targetAggregateType,
        public readonly string $targetAggregateId,
        public readonly string $previousState,
        public readonly string $currentState,
        public readonly bool $approved,
        public readonly int $instanceStatus,
        public readonly ?string $actionedBy,
    ) {
    }

    public function toPayload(): array
    {
        return [
            'event_type'             => self::EVENT_TYPE,
            'tenant_id'              => $this->tenantId,
            'task_id'                => $this->taskId,
            'process_instance_id'    => $this->processInstanceId,
            'target_aggregate_type'  => $this->targetAggregateType,
            'target_aggregate_id'    => $this->targetAggregateId,
            'previous_state'         => $this->previousState,
            'current_state'          => $this->currentState,
            'approved'               => $this->approved,
            'instance_status'        => $this->instanceStatus,
            'actioned_by'            => $this->actionedBy,
        ];
    }
}
