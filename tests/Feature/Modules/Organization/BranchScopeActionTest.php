<?php

namespace Tests\Feature\Modules\Organization;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\Organization\Models\Company;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Services\BranchService;
use App\Modules\Organization\DTOs\UpdateBranchDTO;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use App\Base\Services\ScopeAccessGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * F3: Validate Scope before Action on Branch resources.
 */
class BranchScopeActionTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Company $company;
    protected Branch $branchAllowed;
    protected Branch $branchDenied;

    protected function setUp(): void
    {
        parent::setUp();

        config(['scope.enforcement_mode' => 'gradual']);

        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'BR_SCOPE_ACT',
            'status'      => 1,
        ]);

        $user = User::factory()->create(['status' => 1]);

        TenantUser::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'user_id'   => $user->user_id,
            'status'    => 1,
        ]);

        TenantContext::getInstance()->setTenantId($this->tenant->tenant_id);

        $this->company = Company::withoutGlobalScopes()->create([
            'company_id' => (string) Str::uuid(),
            'tenant_id'  => $this->tenant->tenant_id,
            'code'       => 'C-ACT',
            'name'       => 'Action Company',
            'is_active'  => true,
        ]);

        $this->branchAllowed = Branch::withoutGlobalScopes()->create([
            'branch_id'  => (string) Str::uuid(),
            'tenant_id'  => $this->tenant->tenant_id,
            'company_id' => $this->company->company_id,
            'code'       => 'BR-OK',
            'name'       => 'Allowed',
            'is_active'  => true,
        ]);

        $this->branchDenied = Branch::withoutGlobalScopes()->create([
            'branch_id'  => (string) Str::uuid(),
            'tenant_id'  => $this->tenant->tenant_id,
            'company_id' => $this->company->company_id,
            'code'       => 'BR-NO',
            'name'       => 'Denied',
            'is_active'  => true,
        ]);

        ScopeContext::getInstance()->setScopes([
            [
                'scope_id'     => (string) Str::uuid(),
                'scope_type'   => 'BRANCH',
                'reference_id' => $this->branchAllowed->branch_id,
            ],
        ]);
    }

    protected function tearDown(): void
    {
        ScopeContext::resetInstance();
        TenantContext::resetInstance();
        parent::tearDown();
    }

    /** @test */
    public function update_allowed_branch_succeeds(): void
    {
        $service = new BranchService(new ScopeAccessGuard());

        $dto = new UpdateBranchDTO(
            companyId: $this->company->company_id,
            code: 'BR-OK',
            name: 'Allowed Updated',
            address: null,
            isActive: true,
        );

        $updated = $service->updateBranch($this->branchAllowed->branch_id, $dto);

        $this->assertSame('Allowed Updated', $updated->name);
    }

    /** @test */
    public function update_denied_branch_throws(): void
    {
        $service = new BranchService(new ScopeAccessGuard());

        $dto = new UpdateBranchDTO(
            companyId: $this->company->company_id,
            code: 'BR-NO',
            name: 'Should Fail',
            address: null,
            isActive: true,
        );

        $this->expectException(RuntimeException::class);

        $service->updateBranch($this->branchDenied->branch_id, $dto);
    }

    /** @test */
    public function delete_denied_branch_throws(): void
    {
        $service = new BranchService(new ScopeAccessGuard());

        $this->expectException(RuntimeException::class);

        $service->deleteBranch($this->branchDenied->branch_id);
    }

    /** @test */
    public function strict_mode_blocks_update_when_no_branch_scopes(): void
    {
        config(['scope.enforcement_mode' => 'strict']);

        ScopeContext::resetInstance();
        ScopeContext::getInstance()->setScopes([]);

        $service = new BranchService(new ScopeAccessGuard());

        $dto = new UpdateBranchDTO(
            companyId: $this->company->company_id,
            code: 'BR-OK',
            name: 'Strict Fail',
            address: null,
            isActive: true,
        );

        $this->expectException(RuntimeException::class);

        $service->updateBranch($this->branchAllowed->branch_id, $dto);
    }
}
