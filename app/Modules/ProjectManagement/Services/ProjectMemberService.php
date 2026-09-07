<?php

namespace App\Modules\ProjectManagement\Services;

use App\Modules\ProjectManagement\Models\Project;
use App\Modules\ProjectManagement\Models\ProjectMember;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProjectMemberService
{
    public function listForProject(string $projectId): Collection
    {
        return ProjectMember::query()
            ->where('project_id', $projectId)
            ->where('is_active', true)
            ->orderBy('joined_at')
            ->get();
    }

    public function add(string $projectId, array $data): ProjectMember
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

                $active = ProjectMember::query()
                    ->where('project_id', $projectId)
                    ->where('employee_id', $data['employee_id'])
                    ->where('is_active', true)
                    ->first();
                if ($active) {
                    throw new ConflictHttpException('Employee is already an active member of this project.');
                }

                return ProjectMember::create([
                    'tenant_id'    => $tenantId,
                    'project_id'   => $projectId,
                    'employee_id'  => $data['employee_id'],
                    'project_role' => $data['project_role'],
                    'joined_at'    => $data['joined_at'] ?? now()->toDateString(),
                    'is_active'    => true,
                    'created_by'   => $userId,
                    'row_version'  => 1,
                ]);
            });
        } catch (Exception $e) {
            Log::error('Failed to add project member: ' . $e->getMessage());
            throw $e;
        }
    }

    public function remove(string $memberId): ProjectMember
    {
        $member = ProjectMember::query()->lockForUpdate()->find($memberId);
        if (!$member) {
            throw new NotFoundHttpException('Project member not found.');
        }
        if (!$member->is_active) {
            throw new ConflictHttpException('Member is already inactive.');
        }

        $member->update([
            'is_active'   => false,
            'left_at'     => now()->toDateString(),
            'updated_by'  => Context::get('user_id'),
            'row_version' => ((int) ($member->row_version ?? 1)) + 1,
        ]);

        return $member->fresh();
    }
}
