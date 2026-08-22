<?php

namespace App\Modules\ProjectManagement\DTOs\Project;

use Illuminate\Http\Request;

readonly class CreateProjectDTO
{
    public function __construct(
        public string $project_code,
        public string $name,
        public ?string $description,
        public string $start_date,
        public ?string $end_date,
        public int $status
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            project_code: $request->validated('project_code'),
            name: $request->validated('name'),
            description: $request->validated('description'),
            start_date: $request->validated('start_date'),
            end_date: $request->validated('end_date'),
            status: (int) $request->validated('status', 1)
        );
    }
}