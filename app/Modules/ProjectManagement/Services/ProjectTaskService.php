<?php

namespace App\Modules\ProjectManagement\Services;

use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Models\ProjectTask;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProjectTaskService
{
    public function listForProject(string $projectId): Collection
    {
        return ProjectTask::query()
            ->where('project_id', $projectId)
            ->orderBy('task_code')
            ->get();
    }

    public function create(string $projectId, array $data): ProjectTask
    {
        try {
            return DB::transaction(function () use ($projectId, $data) {
                $tenantId = Context::get('tenant_id');
                $userId = Context::get('user_id');
                if (!$tenantId) {
                    throw new Exception('Tenant Context is missing.');
                }

                $project = Project::query()->find($projectId);
                if (!$project) {
                    throw new NotFoundHttpException('Project not found.');
                }
                if ((int) $project->status === Project::STATUS_COMPLETED || (int) $project->status === Project::STATUS_CANCELLED) {
                    throw new ConflictHttpException('Cannot add tasks to completed or cancelled projects.');
                }

                return ProjectTask::create([
                    'tenant_id'        => $tenantId,
                    'project_id'       => $projectId,
                    'parent_task_id'   => $data['parent_task_id'] ?? null,
                    'task_code'        => $data['task_code'],
                    'title'            => $data['title'],
                    'description'      => $data['description'] ?? null,
                    'status'           => $data['status'] ?? ProjectTask::STATUS_TODO,
                    'priority'         => $data['priority'] ?? ProjectTask::PRIORITY_MEDIUM,
                    'start_date'       => $data['start_date'] ?? null,
                    'due_date'         => $data['due_date'] ?? null,
                    'estimated_hours'  => $data['estimated_hours'] ?? 0,
                    'actual_hours'     => 0,
                    'created_by'       => $userId,
                    'row_version'      => 1,
                ]);
            });
        } catch (Exception $e) {
            Log::error('Failed to create ProjectTask: ' . $e->getMessage());
            throw $e;
        }
    }

    public function start(string $taskId): ProjectTask
    {
        $task = ProjectTask::query()->lockForUpdate()->find($taskId);
        if (!$task) {
            throw new NotFoundHttpException('Task not found.');
        }
        if ((int) $task->status !== ProjectTask::STATUS_TODO) {
            throw new ConflictHttpException('Only todo tasks can be started.');
        }

        $task->update([
            'status'      => ProjectTask::STATUS_IN_PROGRESS,
            'updated_by'  => Context::get('user_id'),
            'row_version' => ((int) ($task->row_version ?? 1)) + 1,
        ]);

        return $task->fresh();
    }

    public function complete(string $taskId, float $actualHours = 0): ProjectTask
    {
        $task = ProjectTask::query()->lockForUpdate()->find($taskId);
        if (!$task) {
            throw new NotFoundHttpException('Task not found.');
        }
        if (!in_array((int) $task->status, [ProjectTask::STATUS_IN_PROGRESS, ProjectTask::STATUS_REVIEW], true)) {
            throw new ConflictHttpException('Only in-progress or review tasks can be completed.');
        }

        $task->update([
            'status'          => ProjectTask::STATUS_DONE,
            'actual_hours'    => $actualHours > 0 ? $actualHours : $task->actual_hours,
            'actual_end_date' => now()->toDateString(),
            'updated_by'      => Context::get('user_id'),
            'row_version'     => ((int) ($task->row_version ?? 1)) + 1,
        ]);

        return $task->fresh();
    }
}
