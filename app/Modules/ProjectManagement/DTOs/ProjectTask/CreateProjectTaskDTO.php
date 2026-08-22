<?php

namespace App\Modules\ProjectManagement\DTOs\ProjectTask;

use Illuminate\Http\Request;

readonly class CreateProjectTaskDTO
{
    public function __construct(
        public string $project_id,
        public ?string $parent_task_id,
        public string $title,
        public ?string $description,
        public string $start_date,
        public string $due_date,
        public int $status,
        public int $priority
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            project_id: $request->validated('project_id'),
            parent_task_id: $request->validated('parent_task_id'),
            title: $request->validated('title'),
            description: $request->validated('description'),
            start_date: $request->validated('start_date'),
            due_date: $request->validated('due_date'),
            status: (int) $request->validated('status', 1),
            priority: (int) $request->validated('priority', 2)
        );
    }
}