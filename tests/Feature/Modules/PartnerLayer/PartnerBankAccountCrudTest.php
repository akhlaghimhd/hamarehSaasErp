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
use App\Modules\PartnerLayer\Models\PartnerBankAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

class PartnerBankAccountCrudTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected string $token;
    protected Partner $partner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['tenant_code' => 'P3_BA', 'status' => 1]);
        $this->user = User::factory()->create(['status' => 1]);

        TenantUser::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'user_id' => $this->user->user_id,
            'status' => 1,
        ]);

        $role = TenantRole::factory()->create([
            'tenant_id' => $this->tenant->tenant_id,
            'code' => 'BANK_MGR',
            'name' => 'Bank Manager',
        ]);

        foreach (['partner.bank_account.view', 'partner.bank_account.create', 'partner.bank_account.update', 'partner.bank_account.delete'] as $code) {
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

        $this->token = $this->user->createToken('ba-test', ['tenant:' . $this->tenant->tenant_id])->plainTextToken;

        $this->partner = Partner::create([
            'partner_id' => (string) Str::uuid(),
            'tenant_id' => $this->tenant->tenant_id,
            'code' => 'BA-P',
            'name' => 'Partner Bank',
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
    public function authorized_user_can_create_bank_account(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/partner-layer/partner-bank-accounts', [
                'partner_id' => $this->partner->partner_id,
                'bank_name' => 'Melli',
                'shaba_number' => 'IR120170000000123456789001',
                'is_active' => true,
            ]);

        $response->assertStatus(201)->assertJsonPath('status', 'success');
        $this->assertDatabaseHas('partner_bank_accounts', [
            'partner_id' => $this->partner->partner_id,
            'shaba_number' => 'IR120170000000123456789001',
        ]);
    }

    #[Test]
    public function duplicate_active_shaba_is_rejected(): void
    {
        PartnerBankAccount::create([
            'partner_bank_account_id' => (string) Str::uuid(),
            'partner_id' => $this->partner->partner_id,
            'bank_name' => 'Melli',
            'shaba_number' => 'IR999999999999999999999999',
            'is_active' => true,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/partner-layer/partner-bank-accounts', [
                'partner_id' => $this->partner->partner_id,
                'bank_name' => 'Saderat',
                'shaba_number' => 'IR999999999999999999999999',
            ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function authorized_user_can_list_and_delete_bank_account(): void
    {
        $account = PartnerBankAccount::create([
            'partner_bank_account_id' => (string) Str::uuid(),
            'partner_id' => $this->partner->partner_id,
            'bank_name' => 'Tejarat',
            'shaba_number' => 'IR111111111111111111111111',
            'is_active' => true,
        ]);

        $list = $this->withHeaders($this->authHeaders())
            ->getJson('/api/partner-layer/partner-bank-accounts?partner_id=' . $this->partner->partner_id);
        $list->assertStatus(200);
        $this->assertNotEmpty($list->json('data'));

        $del = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/partner-layer/partner-bank-accounts/' . $account->partner_bank_account_id);
        $del->assertStatus(200);
    }
}
