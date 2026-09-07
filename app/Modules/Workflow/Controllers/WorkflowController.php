<?php

namespace App\Modules\Workflow\Controllers;

use App\Base\Controller;
use App\Modules\Workflow\Requests\UpsertProcessDefinitionRequest;
use App\Modules\Workflow\Requests\StartProcessInstanceRequest;
use App\Modules\Workflow\Requests\CompleteTaskRequest;
use App\Modules\Workflow\Services\WorkflowEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowController extends Controller
{
    public function __construct(private readonly WorkflowEngineService $engine)
    {
    }

    public function upsertDefinition(UpsertProcessDefinitionRequest $request): JsonResponse
    {
        $v = $request->validated();
        $definition = $this->engine->upsertDefinition(
            code: $v['code'],
            name: $v['name'],
            targetAggregateType: $v['target_aggregate_type'],
            flowGraph: $v['flow_graph'],
            isActive: $v['is_active'] ?? true,
        );

        return response()->json([
            'message' => 'Process definition saved.',
            'data'    => $definition,
        ], 201);
    }

    public function startInstance(StartProcessInstanceRequest $request): JsonResponse
    {
        $v = $request->validated();
        $instance = $this->engine->startInstance(
            definitionCode: $v['definition_code'],
            targetAggregateType: $v['target_aggregate_type'],
            targetAggregateId: $v['target_aggregate_id'],
            contextSnapshot: $v['context_snapshot'] ?? null,
        );

        return response()->json([
            'message' => 'Process instance started.',
            'data'    => $instance,
        ], 201);
    }

    public function showInstance(string $id): JsonResponse
    {
        return response()->json([
            'data' => $this->engine->getInstance($id),
        ]);
    }

    public function worklist(Request $request): JsonResponse
    {
        $request->validate([
            'assigned_type'   => ['required', 'integer', 'in:1,2'],
            'assigned_to_id'  => ['required', 'uuid'],
        ]);

        $tasks = $this->engine->listPendingTasks(
            (int) $request->input('assigned_type'),
            $request->input('assigned_to_id'),
        );

        return response()->json([
            'data' => $tasks,
        ]);
    }

    public function completeTask(string $id, CompleteTaskRequest $request): JsonResponse
    {
        $v = $request->validated();
        $instance = $this->engine->completeTask($id, (bool) $v['approve']);

        return response()->json([
            'message' => 'Task completed.',
            'data'    => $instance,
        ]);
    }
}
