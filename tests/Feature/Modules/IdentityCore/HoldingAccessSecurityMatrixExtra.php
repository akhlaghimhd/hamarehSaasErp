<?php

namespace Tests\Feature\Modules\IdentityCore;

use App\Base\Context\ScopeContext;
use App\Base\Services\HoldingAccessService;
use App\Modules\IdentityCore\Models\TenantScope;
use App\Modules\IdentityCore\Models\TenantUserScope;
use App\Modules\IdentityCore\Services\RoleGrantCeilingService;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

/**
 * Extra matrix scenarios: elevation path, branch-only actor, group-observer grant deny.
 */
trait HoldingAccessSecurityMatrixExtra
{
    private function makeBranchScope(string $name, string $branchId): TenantScope
    {
        return TenantScope::withoutGlobalScopes()->create([
            'scope_id' => (string) Str::uuid(), 'tenant_id' => $this->tenant->tenant_id,
            'scope_name' => $name, 'scope_type' => 'BRANCH', 'reference_id' => $branchId, 'is_active' => true,
        ]);
    }

    private function branchScopePayload(TenantScope $branchScope, string $branchId): array
    {
        return [['scope_id' => $branchScope->scope_id, 'scope_type' => 'BRANCH', 'reference_id' => $branchId, 'scope_name' => 'BR']];
    }

    #[Test]
    public function hsm_x08_elevation_path_group_role_after_primary_scope(): void
    {
        [$ownerUser, $ownerTu] = $this->makeMember(true, $this->primary->company_id, $this->scopePrimary);
        [, $childTu] = $this->makeMember(false, $this->childB->company_id, $this->scopeChild);

        $this->bindActor($ownerUser, $ownerTu, $this->primaryScopePayload(), true);

        $deniedBefore = false;
        try {
            app(RoleGrantCeilingService::class)->assertCanGrantRoleIds(
                $this->tenant->tenant_id,
                (string) $childTu->user_id,
                [(string) $this->roleGroupObserver->tenant_role_id]
            );
        } catch (RuntimeException $e) {
            $deniedBefore = true;
        }

        TenantUserScope::withoutGlobalScopes()->create([
            'assignment_id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->tenant_id,
            'tenant_user_id' => $childTu->tenant_user_id,
            'scope_id' => $this->scopePrimary->scope_id,
        ]);
        app()->forgetInstance(RoleGrantCeilingService::class);

        $allowedAfter = true;
        $detail = '';
        try {
            app(RoleGrantCeilingService::class)->assertCanGrantRoleIds(
                $this->tenant->tenant_id,
                (string) $childTu->user_id,
                [(string) $this->roleGroupObserver->tenant_role_id]
            );
        } catch (\Throwable $e) {
            $allowedAfter = false;
            $detail = $e->getMessage();
        }

        $ok = $deniedBefore && $allowedAfter;
        $this->record(
            'HSM-X08',
            'complex',
            'critical',
            'مسیر ارتقا: بعد از اسکوپ primary نقش group مجاز',
            'deny then allow',
            $ok,
            'ID-ROLE-L05',
            $detail ?: ('before='.($deniedBefore?'deny':'allow').' after='.($allowedAfter?'allow':'deny'))
        );
    }

    #[Test]
    public function hsm_x09_elevation_still_blocks_platform_admin_without_owner(): void
    {
        [$ownerUser, $ownerTu] = $this->makeMember(true, $this->primary->company_id, $this->scopePrimary);
        [, $childTu] = $this->makeMember(false, $this->childB->company_id, $this->scopeChild);

        TenantUserScope::withoutGlobalScopes()->create([
            'assignment_id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->tenant_id,
            'tenant_user_id' => $childTu->tenant_user_id,
            'scope_id' => $this->scopePrimary->scope_id,
        ]);

        $this->bindActor($ownerUser, $ownerTu, $this->primaryScopePayload(), true);

        $denied = false;
        $detail = '';
        try {
            app(RoleGrantCeilingService::class)->assertCanGrantRoleIds(
                $this->tenant->tenant_id,
                (string) $childTu->user_id,
                [(string) $this->rolePlatformAdmin->tenant_role_id]
            );
        } catch (RuntimeException $e) {
            $denied = true;
            $detail = $e->getMessage();
        }
        $this->record(
            'HSM-X09',
            'complex',
            'critical',
            'حتی با اسکوپ primary، platform-admin بدون is_owner رد',
            'DENY',
            $denied,
            'ID-ROLE-L02/L04',
            $detail
        );
    }

    #[Test]
    public function hsm_x10_branch_only_actor_cannot_grant_company_tier(): void
    {
        $branchId = (string) Str::uuid();
        $branchScope = $this->makeBranchScope('BR-1', $branchId);
        [$opUser, $opTu] = $this->makeMember(false, $this->childB->company_id, null);
        TenantUserScope::withoutGlobalScopes()->create([
            'assignment_id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->tenant_id,
            'tenant_user_id' => $opTu->tenant_user_id,
            'scope_id' => $branchScope->scope_id,
        ]);
        [, $targetTu] = $this->makeMember(false, $this->childB->company_id, $this->scopeChild);

        $this->bindActor($opUser, $opTu, $this->branchScopePayload($branchScope, $branchId));

        $denied = false;
        $detail = '';
        try {
            app(RoleGrantCeilingService::class)->assertCanGrantRoleIds(
                $this->tenant->tenant_id,
                (string) $targetTu->user_id,
                [(string) $this->roleCompanyOp->tenant_role_id]
            );
        } catch (RuntimeException $e) {
            $denied = true;
            $detail = $e->getMessage();
        }
        $this->record(
            'HSM-X10',
            'complex',
            'high',
            'بازیگر فقط BRANCH نتواند نقش company بدهد',
            'DENY',
            $denied,
            'ID-ROLE-L03',
            $detail
        );
    }

    #[Test]
    public function hsm_a04_group_observer_cannot_grant_roles(): void
    {
        [$obsUser, $obsTu] = $this->makeMember(false, $this->primary->company_id, $this->scopePrimary);
        [, $targetTu] = $this->makeMember(false, $this->childB->company_id, $this->scopeChild);

        Context::add('security_context', [
            'is_owner' => false,
            'tenant_id' => $this->tenant->tenant_id,
            'tenant_user_id' => $obsTu->tenant_user_id,
            'user_id' => $obsUser->user_id,
            'permissions' => ['identity.group.observe', 'identity.user.view'],
            'roles' => ['group-observer'],
        ]);
        ScopeContext::getInstance()->setScopes($this->primaryScopePayload(), $obsTu->tenant_user_id);
        app()->forgetInstance(HoldingAccessService::class);
        app()->forgetInstance(RoleGrantCeilingService::class);

        $denied = false;
        $detail = '';
        try {
            app(RoleGrantCeilingService::class)->assertCanGrantRoleIds(
                $this->tenant->tenant_id,
                (string) $targetTu->user_id,
                [(string) $this->roleCompanyUser->tenant_role_id]
            );
        } catch (RuntimeException $e) {
            $denied = true;
            $detail = $e->getMessage();
        }
        $this->record(
            'HSM-A04',
            'adversarial',
            'critical',
            'ناظر گروه نتواند نقش اعطا کند',
            'DENY',
            $denied,
            'ID-ROLE-L03',
            $detail
        );
    }
}
