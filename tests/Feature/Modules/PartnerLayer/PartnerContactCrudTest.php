<?php

namespace Tests\Feature\Modules\PartnerLayer;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\TenantRole;
use App\Modules\IdentityCore\Models\TenantPermission;
use App\Modules\IdentityCore\Models\TenantUserRole;
use App\Modules\IdentityCore\Models\TenantRolePermission;
use App\Modules\PartnerLayer\Models\Partner;
use App\Modules\PartnerLayer\Models\PartnerContact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

class PartnerContactCrudTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected string $token;
    protected Partner $partner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['tenant_code' => 'P3_CT', 'status' => 1]);
        $this->user = User::factory()->create(['status' => 1]);

        TenantUser::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'user_id' => $this->user->user_id,
            'status' => 1,
        ]);

        $role = TenantRole::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'code' => 'CONTACT_MGR',
            'name' => 'Contact Manager',
        ]);

        foreach (['partner.contact.view', 'partner.contact.create', 'partner.contact.update', 'partner.contact.delete'] as $code) {
            $perm = TenantPermission::create([
                'tenant_permission_id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->tenant_id,
                'code' => $code,
                'name' => $code,
                'module_name' => 'PartnerLayer',
                'status' => 1,
            ]);
            TenantRolePermission::create([
                'tenant_role_permission_id' => (string) Str::uuid(),
                'tenant_id' => $this->tenant->tenant_id,
                'tenant_role_id' => $role->tenant_role_id,
                'tenant_permission_id' => $perm->tenant_permission_id,
            ]);
        }

        TenantUserRole::create([
            'tenant_user_role_id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->tenant_id,
            'user_id' => $this->user->user_id,
            'tenant_role_id' => $role->tenant_role_id,
        ]);

        $this->token = $this->user->createToken('ct-test', ['tenant:' . $this->tenant->tenant_id])->plainTextToken;

        $this->partner = Partner::create([
            'partner_id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->tenant_id,
            'code' => 'CT-P',
            'name' => 'Partner Contact',
            'status' => 1,
        ]);
    }

    protected function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'X-Tenant-ID' => $this->tenant->tenant_id,
            'Accept' => 'application/json',
        ];
    }

    #[Test]
    public function authorized_user_can_create_contact(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/partner-layer/partner-contacts', [
                'partner_id' => $this->partner->partner_id,
                'first_name' => 'Ali',
                'last_name' => 'Rezaei',
                'is_primary' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.is_primary', true);
    }

    #[Test]
    public function only_one_primary_contact_per_partner(): void
    {
        PartnerContact::create([
            'partner_contact_id' => (string) Str::uuid(),
            'partner_id' => $this->partner->partner_id,
            'first_name' => 'First',
            'last_name' => 'Primary',
            'is_primary' => true,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/partner-layer/partner-contacts', [
                'partner_id' => $this->partner->partner_id,
                'first_name' => 'Second',
                'last_name' => 'Primary',
                'is_primary' => true,
            ]);

        $response->assertStatus(201);

        $primaryCount = PartnerContact::query()
            ->where('partner_id', $this->partner->partner_id)
            ->where('is_primary', true)
            ->count();

        $this->assertSame(1, $primaryCount);
    }

    #[Test]
    public function authorized_user_can_list_and_delete_contact(): void
    {
        $contact = PartnerContact::create([
            'partner_contact_id' => (string) Str::uuid(),
            'partner_id' => $this->partner->partner_id,
            'first_name' => 'Sara',
            'last_name' => 'Karimi',
            'is_primary' => false,
        ]);

        $list = $this->withHeaders($this->authHeaders())
            ->getJson('/api/partner-layer/partner-contacts?partner_id=' . $this->partner->partner_id);
        $list->assertStatus(200);

        $del = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/partner-layer/partner-contacts/' . $contact->partner_contact_id);
        $del->assertStatus(200);
    }
}
