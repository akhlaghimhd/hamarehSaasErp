<?php

namespace App\Modules\SaasAdmin\Services;

use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\Department;
use App\Modules\SaasAdmin\Models\SystemSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Physical purge of soft-deleted rows past retention (Law 1.4 + retention LAW).
 * Platform job only — never exposed as primary tenant UI delete path.
 *
 * P0 target: Organization masters (non-primary company, branch, department).
 */
class SoftDeletePurgeService
{
    /**
     * @return array{
     *   enabled: bool,
     *   cutoff: string,
     *   departments: int,
     *   branches: int,
     *   companies: int,
     *   skipped: list<string>
     * }
     */
    public function purgeOrgMasters(bool $force = false): array
    {
        $enabled = SystemSetting::getBool(SystemSetting::KEY_RETENTION_PURGE_JOB_ENABLED, false);
        if (! $enabled && ! $force) {
            return [
                'enabled'     => false,
                'cutoff'      => '',
                'departments' => 0,
                'branches'    => 0,
                'companies'   => 0,
                'skipped'     => ['purge_job_disabled'],
            ];
        }

        $days = SystemSetting::getInt(SystemSetting::KEY_RETENTION_SOFT_DELETE_DAYS_ORG_MASTERS, 90);
        if ($days < 1) {
            $days = 90;
        }

        $cutoff = Carbon::now()->subDays($days);
        $skipped = [];

        // Order: departments → branches → companies (children before parents)
        $departments = $this->purgeDepartments($cutoff, $skipped);
        $branches = $this->purgeBranches($cutoff, $skipped);
        $companies = $this->purgeCompanies($cutoff, $skipped);

        Log::info('erp.purge-soft-deleted.org_masters', [
            'cutoff'      => $cutoff->toIso8601String(),
            'departments' => $departments,
            'branches'    => $branches,
            'companies'   => $companies,
            'skipped'     => count($skipped),
        ]);

        return [
            'enabled'     => true,
            'cutoff'      => $cutoff->toIso8601String(),
            'departments' => $departments,
            'branches'    => $branches,
            'companies'   => $companies,
            'skipped'     => $skipped,
        ];
    }

    /**
     * @param  list<string>  $skipped
     */
    private function purgeDepartments(Carbon $cutoff, array &$skipped): int
    {
        $count = 0;
        $rows = Department::withoutGlobalScopes()
            ->onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->orderBy('deleted_at')
            ->limit(500)
            ->get();

        foreach ($rows as $dept) {
            $hasLiveChildren = Department::withoutGlobalScopes()
                ->where('parent_department_id', $dept->department_id)
                ->whereNull('deleted_at')
                ->exists();

            if ($hasLiveChildren) {
                $skipped[] = "department:{$dept->department_id}:live_children";
                continue;
            }

            // Soft-deleted children past cutoff are purged in same pass; block if soft-deleted but still present
            $hasAnyChildren = Department::withoutGlobalScopes()
                ->withTrashed()
                ->where('parent_department_id', $dept->department_id)
                ->where('department_id', '!=', $dept->department_id)
                ->exists();

            if ($hasAnyChildren) {
                $skipped[] = "department:{$dept->department_id}:has_child_rows";
                continue;
            }

            $dept->forceDelete();
            $count++;
        }

        return $count;
    }

    /**
     * @param  list<string>  $skipped
     */
    private function purgeBranches(Carbon $cutoff, array &$skipped): int
    {
        $count = 0;
        $rows = Branch::withoutGlobalScopes()
            ->onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->orderBy('deleted_at')
            ->limit(500)
            ->get();

        foreach ($rows as $branch) {
            $hasLiveDepts = Department::withoutGlobalScopes()
                ->where('branch_id', $branch->branch_id)
                ->whereNull('deleted_at')
                ->exists();

            if ($hasLiveDepts) {
                $skipped[] = "branch:{$branch->branch_id}:live_departments";
                continue;
            }

            $hasLiveChildBranches = Branch::withoutGlobalScopes()
                ->where('parent_branch_id', $branch->branch_id)
                ->whereNull('deleted_at')
                ->exists();

            if ($hasLiveChildBranches) {
                $skipped[] = "branch:{$branch->branch_id}:live_child_branches";
                continue;
            }

            // Remaining soft-deleted departments under this branch — require they are gone first
            $hasAnyDepts = Department::withoutGlobalScopes()
                ->withTrashed()
                ->where('branch_id', $branch->branch_id)
                ->exists();

            if ($hasAnyDepts) {
                $skipped[] = "branch:{$branch->branch_id}:has_department_rows";
                continue;
            }

            $branch->forceDelete();
            $count++;
        }

        return $count;
    }

    /**
     * @param  list<string>  $skipped
     */
    private function purgeCompanies(Carbon $cutoff, array &$skipped): int
    {
        $count = 0;
        $rows = Company::withoutGlobalScopes()
            ->onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->orderBy('deleted_at')
            ->limit(200)
            ->get();

        foreach ($rows as $company) {
            if ($company->is_primary) {
                $skipped[] = "company:{$company->company_id}:is_primary";
                continue;
            }

            $hasLiveChildren = Company::withoutGlobalScopes()
                ->where('parent_company_id', $company->company_id)
                ->whereNull('deleted_at')
                ->exists();

            if ($hasLiveChildren) {
                $skipped[] = "company:{$company->company_id}:live_child_companies";
                continue;
            }

            $hasLiveBranches = Branch::withoutGlobalScopes()
                ->where('company_id', $company->company_id)
                ->whereNull('deleted_at')
                ->exists();

            if ($hasLiveBranches) {
                $skipped[] = "company:{$company->company_id}:live_branches";
                continue;
            }

            $hasLiveDepts = Department::withoutGlobalScopes()
                ->where('company_id', $company->company_id)
                ->whereNull('deleted_at')
                ->exists();

            if ($hasLiveDepts) {
                $skipped[] = "company:{$company->company_id}:live_departments";
                continue;
            }

            $hasAnyBranches = Branch::withoutGlobalScopes()
                ->withTrashed()
                ->where('company_id', $company->company_id)
                ->exists();

            if ($hasAnyBranches) {
                $skipped[] = "company:{$company->company_id}:has_branch_rows";
                continue;
            }

            $hasAnyDepts = Department::withoutGlobalScopes()
                ->withTrashed()
                ->where('company_id', $company->company_id)
                ->exists();

            if ($hasAnyDepts) {
                $skipped[] = "company:{$company->company_id}:has_department_rows";
                continue;
            }

            DB::transaction(function () use ($company) {
                $company->forceDelete();
            });
            $count++;
        }

        return $count;
    }
}
