<?php

namespace App\Modules\ProjectManagement\Services;

use App\Modules\ProjectManagement\DTOs\ProjectMember\AddProjectMemberDTO;
use App\Modules\ProjectManagement\Models\ProjectMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProjectMemberService
{
    public function addMember(AddProjectMemberDTO $dto): ProjectMember
    {
        return DB::transaction(function () use ($dto) {
            $member = ProjectMember::create([
                'project_id'   => $dto->project_id,
                'employee_id'  => $dto->employee_id,
                'project_role' => $dto->project_role,
                'joined_at'    => $dto->joined_at,
                'is_active'    => true,
                'created_at'   => now()
            ]);

            $this->publishEventToOutbox($member);

            return $member;
        });
    }

    private function publishEventToOutbox(ProjectMember $member): void
    {
        DB::table('event_outbox')->insert([
            'event_id'       => Str::uuid()->toString(),
            'tenant_id'      => $member->tenant_id,
            'aggregate_type' => 'project_members',
            'aggregate_id'   => $member->member_id,
            'event_type'     => 'project_management.member.added',
            'payload'        => json_encode([
                'member_id'    => $member->member_id,
                'project_id'   => $member->project_id,
                'employee_id'  => $member->employee_id,
                'project_role' => $member->project_role
            ]),
            'status'         => 1, // Pending
            'retry_count'    => 0,
            'created_at'     => now(),
        ]);
    }
}