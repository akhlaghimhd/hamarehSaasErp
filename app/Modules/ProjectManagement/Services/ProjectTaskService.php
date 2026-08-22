<?php

namespace App\Modules\ProjectManagement\Services;

use App\Modules\ProjectManagement\DTOs\ProjectTask\CreateProjectTaskDTO;
use App\Modules\ProjectManagement\Models\ProjectTask;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProjectTaskService
{
    public function createTask(CreateProjectTaskDTO $dto): ProjectTask
    {
        return DB::transaction(function () use ($dto) {
            $task = ProjectTask::create([
                'project_id'     => $dto->project_id,
                'parent_task_id' => $dto->parent_task_id,
                'title'          => $dto->title,
                'description'    => $dto->description,
                'start_date'     => $dto->start_date,
                'due_date'       => $dto->due_date,
                'status'         => $dto->status,
                'priority'       => $dto->priority,
                'row_version'    => 1
            ]);

            $this->publishEventToOutbox($task);

            return $task;
        });
    }

    private function publishEventToOutbox(ProjectTask $task): void
    {
        DB::table('event_outbox')->insert([
            'event_id'       => Str::uuid()->toString(),
            'tenant_id'      => $task->tenant_id,
            'aggregate_type' => 'project_tasks',
            'aggregate_id'   => $task->task_id,
            'event_type'     => 'project_management.task.created',
            'payload'        => json_encode([
                'task_id'    => $task->task_id,
                'project_id' => $task->project_id,
                'title'      => $task->title,
                'status'     => $task->status
            ]),
            'status'         => 1, // Pending
            'retry_count'    => 0,
            'created_at'     => now(),
        ]);
    }
}