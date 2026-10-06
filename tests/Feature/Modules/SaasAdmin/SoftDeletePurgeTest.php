<?php

namespace Tests\Feature\Modules\SaasAdmin;

use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\Department;
use App\Modules\SaasAdmin\Models\SystemSetting;
use App\Modules\SaasAdmin\Services\SoftDeletePurgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SoftDeletePurgeTest extends TestCase
{
    use RefreshDatabase;

    private function tenantId(): string
    {
        return (string) Str::uuid();
    }

    private function enablePurge(int $days = 30): void
    {
        SystemSetting::query()->updateOrCreate(
            ['setting_key' => SystemSetting::KEY_RETENTION_PURGE_JOB_ENABLED],
            [
                'system_setting_id' => (string) Str::uuid(),
                'setting_value'     => 'true',
                'description'       => 'test',
                'row_version'       => 1,
            ]
        );
        SystemSetting::query()->updateOrCreate(
            ['setting_key' => SystemSetting::KEY_RETENTION_SOFT_DELETE_DAYS_ORG_MASTERS],
            [
                'system_setting_id' => (string) Str::uuid(),
                'setting_value'     => (string) $days,
                'description'       => 'test',
                'row_version'       => 1,
            ]
        );
    }

    #[Test]
    public function purge_is_no_op_when_job_disabled(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['setting_key' => SystemSetting::KEY_RETENTION_PURGE_JOB_ENABLED],
            [
                'system_setting_id' => (string) Str::uuid(),
                'setting_value'     => 'false',
                'row_version'       => 1,
            ]
        );

        $result = app(SoftDeletePurgeService::class)->purgeOrgMasters(force: false);

        $this->assertFalse($result['enabled']);
        $this->assertSame(0, $result['companies']);
    }

    #[Test]
    public function it_force_deletes_soft_deleted_department_past_retention(): void
    {
        $this->enablePurge(30);
        $tenantId = $this->tenantId();

        $company = Company::withoutGlobalScopes()->create([
            'tenant_id'   => $tenantId,
            'code'        => 'C1',
            'name'        => 'Co',
            'legal_name'  => 'Co Legal',
            'is_primary'  => true,
            'entity_kind' => Company::ENTITY_KIND_OPERATING,
            'status'      => 1,
            'is_active'   => true,
            'row_version' => 1,
        ]);

        $branch = Branch::withoutGlobalScopes()->create([
            'tenant_id'   => $tenantId,
            'company_id'  => $company->company_id,
            'code'        => 'HQ',
            'name'        => 'HQ',
            'branch_kind' => Branch::KIND_OFFICE,
            'is_active'   => true,
            'row_version' => 1,
        ]);

        $dept = Department::withoutGlobalScopes()->create([
            'tenant_id'   => $tenantId,
            'company_id'  => $company->company_id,
            'branch_id'   => $branch->branch_id,
            'code'        => 'D1',
            'name'        => 'Dept',
            'is_active'   => true,
            'row_version' => 1,
        ]);

        $dept->deleted_at = now()->subDays(60);
        $dept->save();

        $result = app(SoftDeletePurgeService::class)->purgeOrgMasters(force: true);

        $this->assertSame(1, $result['departments']);
        $this->assertNull(
            Department::withoutGlobalScopes()->withTrashed()->find($dept->department_id)
        );
    }

    #[Test]
    public function it_never_purges_primary_company_even_if_soft_deleted(): void
    {
        $this->enablePurge(1);
        $tenantId = $this->tenantId();

        $company = Company::withoutGlobalScopes()->create([
            'tenant_id'   => $tenantId,
            'code'        => 'PRIM',
            'name'        => 'Primary',
            'legal_name'  => 'Primary Legal',
            'is_primary'  => true,
            'entity_kind' => Company::ENTITY_KIND_OPERATING,
            'status'      => 1,
            'is_active'   => true,
            'row_version' => 1,
        ]);

        $company->deleted_at = now()->subDays(100);
        $company->save();

        $result = app(SoftDeletePurgeService::class)->purgeOrgMasters(force: true);

        $this->assertSame(0, $result['companies']);
        $this->assertNotNull(
            Company::withoutGlobalScopes()->withTrashed()->find($company->company_id)
        );
        $this->assertTrue(
            collect($result['skipped'])->contains(
                fn ($s) => str_contains($s, 'is_primary')
            )
        );
    }
}
