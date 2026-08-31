<?php

namespace Tests\Feature\Modules\IdentityCore;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\IdentityCore\Models\TenantPermission;
use App\Modules\IdentityCore\Models\TenantUserRole;
use App\Modules\IdentityCore\Models\TenantRolePermission;
use App\Modules\IdentityCore\Models\UserProfile;
use App\Base\Context\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

class ProfileManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected User $otherMember;
    protected string $token;
    protected string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'PROF_TEST',
            'status'      => 1,
        ]);

        $this->user = User::factory()->create(['status' => 1]);
        $this->otherMember = User::factory()->create(['status' => 1]);

        TenantUser::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'user_id'   => $this->user->user_id,
            'status'    => 1,
        ]);

        TenantUser::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'user_id'   => $this->otherMember->user_id,
            'status'    => 1,
        ]);

        $role = TenantRole::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'code'      => 'profile-manager',
            'name'      => 'Profile Manager',
            'status'    => 1,
        ]);

        foreach ([
            'identity.profile.view',
            'identity.profile.update',
            'identity.profile.delete',
        ] as $code) {
            $perm = TenantPermission::create([
                'tenant_permission_id' => (string) Str::uuid(),
                'tenant_id'            => $this->tenant->tenant_id,
                'code'                 => $code,
                'name'                 => $code,
                'module_name'          => 'Identity',
                'status'               => 1,
            ]);

            TenantRolePermission::create([
                'tenant_role_permission_id' => (string) Str::uuid(),
                'tenant_id'                 => $this->tenant->tenant_id,
                'tenant_role_id'            => $role->tenant_role_id,
                'tenant_permission_id'      => $perm->tenant_permission_id,
            ]);
        }

        TenantUserRole::create([
            'tenant_user_role_id' => (string) Str::uuid(),
            'tenant_id'           => $this->tenant->tenant_id,
            'user_id'             => $this->user->user_id,
            'tenant_role_id'      => $role->tenant_role_id,
        ]);

        $this->adminToken = $this->user->createToken(
            'profile-admin',
            ['tenant:' . $this->tenant->tenant_id]
        )->plainTextToken;

        $this->token = $this->otherMember->createToken(
            'profile-member',
            ['tenant:' . $this->tenant->tenant_id]
        )->plainTextToken;

        TenantContext::getInstance()->setTenantId($this->tenant->tenant_id);
        app()->instance('current_tenant_id', $this->tenant->tenant_id);
    }

    protected function headers(string $token): array
    {
        return [
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-ID'   => $this->tenant->tenant_id,
            'Accept'        => 'application/json',
        ];
    }

    #[Test]
    public function authenticated_user_can_upsert_own_profile(): void
    {
        $response = $this->withHeaders($this->headers($this->token))
            ->putJson('/api/identity-core/identity/profiles/me', [
                'phone'       => '+989121234567',
                'gender'      => 1,
                'description' => 'Self profile',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.user_id', $this->otherMember->user_id)
            ->assertJsonPath('data.phone', '+989121234567');

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $this->otherMember->user_id,
            'phone'   => '+989121234567',
        ]);

        $this->assertDatabaseHas('event_outbox', [
            'tenant_id'  => $this->tenant->tenant_id,
            'event_type' => 'identity.user_profile.created.v1',
            'status'     => 1,
        ]);
    }

    #[Test]
    public function authenticated_user_can_get_own_profile_after_create(): void
    {
        UserProfile::create([
            'profile_id' => (string) Str::uuid(),
            'user_id'    => $this->otherMember->user_id,
            'phone'      => '+989000000001',
        ]);

        $response = $this->withHeaders($this->headers($this->token))
            ->getJson('/api/identity-core/identity/profiles/me');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.phone', '+989000000001');
    }

    #[Test]
    public function authorized_admin_can_show_and_update_member_profile(): void
    {
        UserProfile::create([
            'profile_id'  => (string) Str::uuid(),
            'user_id'     => $this->otherMember->user_id,
            'description' => 'Original',
        ]);

        $show = $this->withHeaders($this->headers($this->adminToken))
            ->getJson('/api/identity-core/identity/profiles/' . $this->otherMember->user_id);

        $show->assertStatus(200)
            ->assertJsonPath('data.user_id', $this->otherMember->user_id);

        $update = $this->withHeaders($this->headers($this->adminToken))
            ->putJson('/api/identity-core/identity/profiles/' . $this->otherMember->user_id, [
                'description' => 'Updated by admin',
                'gender'      => 2,
            ]);

        $update->assertStatus(200)
            ->assertJsonPath('data.description', 'Updated by admin');

        $this->assertDatabaseHas('event_outbox', [
            'tenant_id'  => $this->tenant->tenant_id,
            'event_type' => 'identity.user_profile.updated.v1',
            'status'     => 1,
        ]);
    }

    #[Test]
    public function authorized_admin_can_soft_delete_profile(): void
    {
        $profile = UserProfile::create([
            'profile_id' => (string) Str::uuid(),
            'user_id'    => $this->otherMember->user_id,
            'phone'      => '+989111111111',
        ]);

        $response = $this->withHeaders($this->headers($this->adminToken))
            ->deleteJson('/api/identity-core/identity/profiles/' . $this->otherMember->user_id);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertSoftDeleted('user_profiles', [
            'profile_id' => $profile->profile_id,
            'user_id'    => $this->otherMember->user_id,
        ]);

        $this->assertDatabaseHas('event_outbox', [
            'tenant_id'  => $this->tenant->tenant_id,
            'event_type' => 'identity.user_profile.deleted.v1',
            'status'     => 1,
        ]);
    }

    #[Test]
    public function unauthorized_member_cannot_update_or_delete_other_profile(): void
    {
        UserProfile::create([
            'profile_id' => (string) Str::uuid(),
            'user_id'    => $this->user->user_id,
            'phone'      => '+989222222222',
        ]);

        $this->withHeaders($this->headers($this->token))
            ->putJson('/api/identity-core/identity/profiles/' . $this->user->user_id, [
                'description' => 'Hacked',
            ])
            ->assertStatus(403);

        $this->withHeaders($this->headers($this->token))
            ->deleteJson('/api/identity-core/identity/profiles/' . $this->user->user_id)
            ->assertStatus(403);
    }

    #[Test]
    public function cannot_access_profile_of_user_outside_tenant(): void
    {
        $outsider = User::factory()->create(['status' => 1]);

        UserProfile::create([
            'profile_id' => (string) Str::uuid(),
            'user_id'    => $outsider->user_id,
            'phone'      => '+989333333333',
        ]);

        $this->withHeaders($this->headers($this->adminToken))
            ->getJson('/api/identity-core/identity/profiles/' . $outsider->user_id)
            ->assertStatus(404);
    }
}
