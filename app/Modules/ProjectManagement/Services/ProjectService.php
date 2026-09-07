<?php

namespace App\Modules\ProjectManagement\Services;

use App\Modules\ProjectManagement\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProjectService
{
    public function list(): Collection
    {
        return Project::query()->orderByDesc('created_at')->get();
    }

    public function getById(string $id): Project
    {
        $project = Project::query()->find($id);
        if (!$project) {
            throw new NotFoundHttpException('Project not found.');
        }

        return $project;
    }

    public function create(array $data): Project
    {
        try {
            return DB::transaction(function () use ($data) {
                $tenantId = Context::get('tenant_id');
                $userId = Context::get('user_id');
                if (!$tenantId) {
                    throw new Exception('Tenant Context is missing.');
                }

                return Project::create([
                    'tenant_id'    => $tenantId,
                    'project_code' => $data['project_code'],
                    'name'         => $data['name'],
                    'description'  => $data['description'] ?? null,
                    'start_date'   => $data['start_date'],
                    'end_date'     => $data['end_date'] ?? null,
                    'status'       => $data['status'] ?? Project::STATUS_PLANNING,
                    'budget'       => $data['budget'] ?? 0,
                    'created_by'   => $userId,
                    'row_version'  => 1,
                ]);
            });
        } catch (Exception $e) {
            Log::error('Failed to create Project: ' . $e->getMessage());
            throw $e;
        }
    }

    public function activate(string $id): Project
    {
        $project = Project::query()->lockForUpdate()->find($id);
        if (!$project) {
            throw new NotFoundHttpException('Project not found.');
        }
        if ((int) $project->status !== Project::STATUS_PLANNING && (int) $project->status !== Project::STATUS_ON_HOLD) {
            throw new ConflictHttpException('Only planning or on-hold projects can be activated.');
        }

        $project->update([
            'status'      => Project::STATUS_ACTIVE,
            'updated_by'  => Context::get('user_id'),
            'row_version' => ((int) ($project->row_version ?? 1)) + 1,
        ]);

        return $project->fresh();
    }

    public function complete(string $id): Project
    {
        $project = Project::query()->lockForUpdate()->find($id);
        if (!$project) {
            throw new NotFoundHttpException('Project not found.');
        }
        if ((int) $project->status !== Project::STATUS_ACTIVE) {
            throw new ConflictHttpException('Only active projects can be completed.');
        }

        $project->update([
            'status'          => Project::STATUS_COMPLETED,
            'actual_end_date' => now()->toDateString(),
            'updated_by'      => Context::get('user_id'),
            'row_version'     => ((int) ($project->row_version ?? 1)) + 1,
        ]);

        return $project->fresh();
    }
}
