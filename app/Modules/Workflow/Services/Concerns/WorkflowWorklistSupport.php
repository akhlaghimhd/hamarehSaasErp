<?php

namespace App\Modules\Workflow\Services\Concerns;

use App\Modules\IdentityCore\Models\TenantUserRole;
use App\Modules\Workflow\Models\Task;
use Illuminate\Support\Facades\Context;
use Exception;

/**
 * L6-WF-06 / L6-WF-07 – Worklist helpers by user roles and external partners.
 */
trait WorkflowWorklistSupport
{
    /**
     * @return \Illuminate\Support\Collection<int, Task>
     */
    public function listPendingTasksForCurrentUser()
    {
        $userId = Context::get('user_id');
        $tenantId = Context::get('tenant_id');
        if (!$userId || !$tenantId) {
            throw new Exception('User/Tenant Context is missing.');
        }

        $roleIds = TenantUserRole::query()
            ->where('user_id', $userId)
            ->pluck('tenant_role_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($roleIds)) {
            return collect();
        }

        return Task::query()
            ->where('assigned_type', self::ASSIGN_INTERNAL_ROLE)
            ->whereIn('assigned_to_id', $roleIds)
            ->where('status', self::TASK_PENDING)
            ->orderBy('created_at')
            ->with('instance')
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Task>
     */
    public function listPendingTasksForExternalPartner(string $businessPartnerId)
    {
        return $this->listPendingTasks(self::ASSIGN_EXTERNAL_BP, $businessPartnerId);
    }
}
