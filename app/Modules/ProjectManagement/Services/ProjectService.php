<?php

namespace App\Modules\ProjectManagement\Services;

use App\Modules\ProjectManagement\DTOs\Project\CreateProjectDTO;
use App\Modules\ProjectManagement\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProjectService
{
    public function createProject(CreateProjectDTO $dto): Project
    {
        return DB::transaction(function () use ($dto) {
            // 1. Create the project entity
            $project = Project::create([
                'project_code' => $dto->project_code,
                'name'         => $dto->name,
                'description'  => $dto->description,
                'start_date'   => $dto->start_date,
                'end_date'     => $dto->end_date,
                'status'       => $dto->status,
                'row_version'  => 1
            ]);

            // 2. Publish Async Event to event_outbox (Transactional Outbox Pattern)
            $this->publishEventToOutbox($project);

            return $project;
        });
    }

    private function publishEventToOutbox(Project $project): void
    {
        DB::table('event_outbox')->insert([
            'event_id'       => Str::uuid()->toString(),
            'tenant_id'      => $project->tenant_id,
            'aggregate_type' => 'projects',
            'aggregate_id'   => $project->project_id,
            'event_type'     => 'project_management.project.created',
            'payload'        => json_encode([
                'project_id'   => $project->project_id,
                'project_code' => $project->project_code,
                'name'         => $project->name,
                'status'       => $project->status
            ]),
            'status'         => 1, // 1: Pending
            'retry_count'    => 0,
            'created_at'     => now(),
        ]);
    }
}