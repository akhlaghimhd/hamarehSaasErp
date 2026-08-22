<?php

namespace App\Modules\ProjectManagement\DTOs\ProjectMember;

use Illuminate\Http\Request;

readonly class AddProjectMemberDTO
{
    public function __construct(
        public string $project_id,
        public string $employee_id,
        public string $project_role,
        public string $joined_at
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            project_id: $request->validated('project_id'),
            employee_id: $request->validated('employee_id'),
            project_role: $request->validated('project_role'),
            joined_at: $request->validated('joined_at', now()->format('Y-m-d'))
        );
    }
}