<?php

namespace Tests\Feature\Modules\IdentityCore;

use App\Base\Context\TenantContext;
use App\Modules\IdentityCore\Models\TenantPermission;
use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\IdentityCore\Models\TenantRolePermission;
use App\Modules\IdentityCore\Models\TenantScope;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\TenantUserRole;
use App\Modules\IdentityCore\Models\TenantUserScope;
use App\Modules\IdentityCore\Models\User;
use App\Modules\Organization\Models\Company;
use App\Modules\SaasPlatform\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\SecurityMatrixReporter;
use Tests\TestCase;

/**
 * HTTP layer of holding role-grant security (same routes the FE calls).
 * Completes matrix without manual UI — POST /api/v1/identity-core/identity/roles/assign
 */
class HoldingAccessHttpSecurityMatrixTest extends TestCase
{
    use RefreshDatabase;

    private static bool $reporterStarted = false;

    protected Tenant $tenant;
    protected Company $primary;
    protected Company $childB;
    protected Company $peer;
    protected TenantScope $scopePrimary;
    protected TenantScope $scopeChild;
    protected TenantScope $scopePeer;
    protected TenantRole $rolePlatformAdmin;
    protected TenantRole $roleGroupObserver;
    protected TenantRole $roleCompanyOp;
    protected User $ownerUser;
    protected TenantUser $ownerTu;
    protected User $childUser;
    protected TenantUser $childTu;
    protected User $peerUser;
    protected TenantUser $peerTu;
    protected User $opUser;
    protected TenantUser $opTu;
    protected string $ownerToken;
    protected string $opToken;

    protected function setUp(): void
    {
        parent::setUp();
        if (! self::$reporterStarted) {
            SecurityMatrixReporter::reset();
            self::$reporterStarted = true;
        }

        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'HSMH_'.substr((string) Str::uuid(), 0, 6),
            'status'      => 1,
        ]);
        TenantContext::getInstance()->setTenantId($this->tenant->tenant_id);
        app()->instance('current_tenant_id', $this->tenant->tenant_id);

        $this->primary = $this->makeCompany('PRIMARY', true, null);
        $this->childB = $this->makeCompany('CHILD-B', false, $this->primary->company_id);
        $this->peer = $this->makeCompany('PEER', false, null);

        $this->scopePrimary = $this->makeScope('PRIMARY', $this->primary->company_id);
        $this->scopeChild = $this->makeScope('CHILD-B', $this->childB->company_id);
        $this->scopePeer = $this->makeScope('PEER', $this->peer->company_id);

        $this->rolePlatformAdmin = $this->makeRole('platform-admin', 'مالک فنی پلتفرم');
        $this->roleGroupObserver = $this->makeRole('group-observer', 'ناظر گروه');
        $this->roleCompanyOp = $this->makeRole('company-operator', 'اپراتور شرکت');

        [$this->ownerUser, $this->ownerTu] = $this->makeMember(true, $this->primary->company_id, $this->scopePrimary);
        [$this->childUser, $this->childTu] = $this->makeMember(false, $this->childB->company_id, $this->scopeChild);
        [$this->peerUser, $this->peerTu] = $this->makeMember(false, $this->peer->company_id, $this->scopePeer);
        [$this->opUser, $this->opTu] = $this->makeMember(false, $this->childB->company_id, $this->scopeChild);

        $this->grantAssignPermission($this->ownerUser, $this->ownerTu);
        $this->grantAssignPermission($this->opUser, $this->opTu);

        $this->ownerToken = $this->ownerUser->createToken('test', ['tenant:'.$this->tenant->tenant_id])->plainTextToken;
        $this->opToken = $this->opUser->createToken('test', ['tenant:'.$this->tenant->tenant_id])->plainTextToken;
    }

    protected function tearDown(): void
    {
        SecurityMatrixReporter::flush('holding_access_http');
        parent::tearDown();
    }

    private function makeCompany(string $code, bool $primary, ?string $parentId): Company
    {
        return Company::withoutGlobalScopes()->create([
            'company_id'        => (string) Str::uuid(),
            'tenant_id'         => $this->tenant->tenant_id,
            'code'              => $code,
            'name'              => $code,
            'legal_name'        => $code.' Legal',
            'is_active'         => true,
            'is_primary'        => $primary,
            'parent_company_id' => $parentId,
        ]);
    }

    private function makeScope(string $name, string $companyId): TenantScope
    {
        return TenantScope::withoutGlobalScopes()->create([
            'scope_id'     => (string) Str::uuid(),
            'tenant_id'    => $this->tenant->tenant_id,
            'scope_name'   => $name,
            'scope_type'   => 'COMPANY',
            'reference_id' => $companyId,
            'is_active'    => true,
        ]);
    }

    private function makeRole(string $code, string $name): TenantRole
    {
        return TenantRole::withoutGlobalScopes()->create([
            'tenant_role_id'    => (string) Str::uuid(),
            'tenant_id'         => $this->tenant->tenant_id,
            'code'              => $code,
            'name'              => $name,
            'status'            => 1,
            'is_system_default' => true,
        ]);
    }

    private function makeMember(bool $owner, ?string $home, ?TenantScope $scope): array
    {
        $user = User::factory()->create(['status' => 1]);
        $tu = TenantUser::factory()->create([
            'tenant_id'       => $this->tenant->tenant_id,
            'user_id'         => $user->user_id,
            'status'          => 1,
            'is_owner'        => $owner,
            'home_company_id' => $home,
        ]);
        if ($scope) {
            TenantUserScope::withoutGlobalScopes()->create([
                'assignment_id'  => (string) Str::uuid(),
                'tenant_id'      => $this->tenant->tenant_id,
                'tenant_user_id' => $tu->tenant_user_id,
                'scope_id'       => $scope->scope_id,
            ]);
        }

        return [$user, $tu];
    }

    private function grantAssignPermission(User $user, TenantUser $tu): void
    {
        $role = TenantRole::withoutGlobalScopes()->create([
            'tenant_role_id' => (string) Str::uuid(),
            'tenant_id'      => $this->tenant->tenant_id,
            'code'           => 'assign-cap-'.substr((string) Str::uuid(), 0, 8),
            'name'           => 'Assign Cap',
            'status'         => 1,
        ]);
        foreach (['identity.role.view', 'identity.role.assign', 'identity.user.view'] as $code) {
            $perm = TenantPermission::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $this->tenant->tenant_id, 'code' => $code],
                [
                    'tenant_permission_id' => (string) Str::uuid(),
                    'name'                 => $code,
                    'module_name'          => 'Identity',
                    'status'               => 1,
                ]
            );
            TenantRolePermission::withoutGlobalScopes()->create([
                'tenant_role_permission_id' => (string) Str::uuid(),
                'tenant_id'                 => $this->tenant->tenant_id,
                'tenant_role_id'            => $role->tenant_role_id,
                'tenant_permission_id'      => $perm->tenant_permission_id,
            ]);
        }
        TenantUserRole::withoutGlobalScopes()->create([
            'tenant_user_role_id' => (string) Str::uuid(),
            'tenant_id'           => $this->tenant->tenant_id,
            'user_id'             => $user->user_id,
            'tenant_role_id'      => $role->tenant_role_id,
        ]);
    }

    private function headers(string $token): array
    {
        return [
            'Authorization' => 'Bearer '.$token,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ];
    }

    private function record(string $id, string $title, string $expected, bool $ok, string $detail = ''): void
    {
        SecurityMatrixReporter::record($id, 'http', 'critical', $title, $expected, $ok ? 'PASS' : 'FAIL', 'ADR-ID-ORG-006 HTTP', $detail);
        $this->assertTrue($ok, "[$id] $title — $detail");
    }

    private function postAssign(string $token, string $targetUserId, array $roleIds)
    {
        return $this->withHeaders($this->headers($token))
            ->postJson('/api/v1/identity-core/identity/roles/assign', [
                'user_id'  => $targetUserId,
                'role_ids' => $roleIds,
            ]);
    }

    #[Test]
    public function http_owner_denied_platform_admin_on_child_user(): void
    {
        $res = $this->postAssign(
            $this->ownerToken,
            (string) $this->childUser->user_id,
            [(string) $this->rolePlatformAdmin->tenant_role_id]
        );
        $ok = $res->status() === 403;
        $this->record('HSM-HTTP-01', 'HTTP: platform-admin به CHILD', '403', $ok, 'status='.$res->status().' body='.mb_substr($res->getContent(), 0, 200));
    }

    #[Test]
    public function http_owner_denied_group_observer_on_child_user(): void
    {
        $res = $this->postAssign(
            $this->ownerToken,
            (string) $this->childUser->user_id,
            [(string) $this->roleGroupObserver->tenant_role_id]
        );
        $ok = $res->status() === 403;
        $this->record('HSM-HTTP-02', 'HTTP: group-observer به CHILD', '403', $ok, 'status='.$res->status().' body='.mb_substr($res->getContent(), 0, 200));
    }

    #[Test]
    public function http_owner_allowed_company_role_on_child_user(): void
    {
        $res = $this->postAssign(
            $this->ownerToken,
            (string) $this->childUser->user_id,
            [(string) $this->roleCompanyOp->tenant_role_id]
        );
        $ok = $res->status() === 200;
        $this->record('HSM-HTTP-03', 'HTTP: company-operator به CHILD توسط owner', '200', $ok, 'status='.$res->status().' body='.mb_substr($res->getContent(), 0, 200));
    }

    #[Test]
    public function http_company_op_denied_assign_on_sibling(): void
    {
        $res = $this->postAssign(
            $this->opToken,
            (string) $this->peerUser->user_id,
            [(string) $this->roleCompanyOp->tenant_role_id]
        );
        $ok = in_array($res->status(), [403, 404], true);
        $this->record('HSM-HTTP-04', 'HTTP: company-op assign به sibling', '403/404', $ok, 'status='.$res->status().' body='.mb_substr($res->getContent(), 0, 200));
    }

    #[Test]
    public function http_elevation_then_group_role_allowed(): void
    {
        TenantUserScope::withoutGlobalScopes()->create([
            'assignment_id'  => (string) Str::uuid(),
            'tenant_id'      => $this->tenant->tenant_id,
            'tenant_user_id' => $this->childTu->tenant_user_id,
            'scope_id'       => $this->scopePrimary->scope_id,
        ]);

        $res = $this->postAssign(
            $this->ownerToken,
            (string) $this->childUser->user_id,
            [(string) $this->roleGroupObserver->tenant_role_id]
        );
        $ok = $res->status() === 200;
        $this->record('HSM-HTTP-05', 'HTTP: بعد از اسکوپ primary نقش group مجاز', '200', $ok, 'status='.$res->status().' body='.mb_substr($res->getContent(), 0, 200));
    }
}
