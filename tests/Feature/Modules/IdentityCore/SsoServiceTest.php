<?php

namespace Tests\Feature\Modules\IdentityCore;

use App\Modules\IdentityCore\Models\TenantSsoProvider;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\UserCredential;
use App\Modules\IdentityCore\Models\UserSsoIdentity;
use App\Modules\IdentityCore\Services\SsoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SsoServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantId;

    private User $user;

    private SsoService $sso;

    private TenantSsoProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = (string) Str::uuid();
        DB::table('tenants')->insert([
            'tenant_id'   => $this->tenantId,
            'tenant_code' => 'SSO1',
            'tenant_name' => 'SSO Tenant',
            'slug'        => 'sso-tenant',
            'status'      => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$this->tenantId]);

        $this->user = User::factory()->create([
            'email'  => 'sso.user@example.com',
            'mobile' => '09123334455',
            'status' => 1,
        ]);

        UserCredential::create([
            'credential_id'       => (string) Str::uuid(),
            'user_id'             => $this->user->user_id,
            'password_hash'       => bcrypt('Secure9!'),
            'must_set_password'   => false,
            'authentication_type' => 1,
            'is_verified'         => true,
            'two_factor_enabled'  => false,
            'failed_login_count'  => 0,
        ]);

        TenantUser::create([
            'tenant_user_id' => (string) Str::uuid(),
            'tenant_id'      => $this->tenantId,
            'user_id'        => $this->user->user_id,
            'status'         => 1,
            'is_owner'       => true,
        ]);

        $this->sso = app(SsoService::class);

        $this->provider = $this->sso->upsertProvider($this->tenantId, [
            'code'                   => 'azure_ad',
            'name'                   => 'Azure AD',
            'issuer'                 => 'https://login.microsoftonline.com/tenant/v2.0',
            'authorization_endpoint' => 'https://login.microsoftonline.com/tenant/oauth2/v2.0/authorize',
            'token_endpoint'         => 'https://login.microsoftonline.com/tenant/oauth2/v2.0/token',
            'client_id'              => 'client-123',
            'client_secret'          => 'secret-xyz',
            'is_enabled'             => true,
            'auto_provision'         => false,
        ]);
    }

    #[Test]
    public function begin_authorization_returns_url_and_state(): void
    {
        $result = $this->sso->beginAuthorization(
            $this->tenantId,
            'azure_ad',
            'https://app.example.com/sso/callback'
        );

        $this->assertNotEmpty($result['state']);
        $this->assertStringContainsString('response_type=code', $result['authorization_url']);
        $this->assertStringContainsString('client_id=client-123', $result['authorization_url']);
    }

    #[Test]
    public function login_with_claims_links_identity_and_issues_session(): void
    {
        $session = $this->sso->completeLoginWithClaims($this->tenantId, $this->provider, [
            'sub'   => 'oidc-sub-001',
            'email' => 'sso.user@example.com',
        ]);

        $this->assertArrayHasKey('access_token', $session);
        $this->assertSame($this->tenantId, $session['tenant_id'] ?? $session['active_tenant_id'] ?? null);

        $this->assertTrue(
            UserSsoIdentity::query()
                ->where('user_id', $this->user->user_id)
                ->where('external_subject', 'oidc-sub-001')
                ->exists()
        );
    }

    #[Test]
    public function login_rejects_unknown_subject_without_auto_provision(): void
    {
        $this->expectException(HttpException::class);

        $this->sso->completeLoginWithClaims($this->tenantId, $this->provider, [
            'sub'   => 'unknown-sub',
            'email' => 'nobody@example.com',
        ]);
    }

    #[Test]
    public function list_enabled_providers_hides_disabled(): void
    {
        $this->sso->upsertProvider($this->tenantId, [
            'code'                   => 'okta',
            'name'                   => 'Okta',
            'issuer'                 => 'https://example.okta.com',
            'authorization_endpoint' => 'https://example.okta.com/oauth2/v1/authorize',
            'token_endpoint'         => 'https://example.okta.com/oauth2/v1/token',
            'client_id'              => 'okta-client',
            'is_enabled'             => false,
        ]);

        $list = $this->sso->listEnabledProviders($this->tenantId);
        $codes = array_column($list, 'code');

        $this->assertContains('azure_ad', $codes);
        $this->assertNotContains('okta', $codes);
    }

    #[Test]
    public function saml_begin_authorization_builds_authn_request(): void
    {
        $this->sso->upsertProvider($this->tenantId, [
            'code'                   => 'adfs',
            'name'                   => 'ADFS',
            'protocol'               => 'SAML',
            'issuer'                 => 'https://sp.example.com/metadata',
            'authorization_endpoint' => 'https://idp.example.com/sso',
            'client_id'              => 'https://sp.example.com/metadata',
            'is_enabled'             => true,
        ]);

        $result = $this->sso->beginAuthorization(
            $this->tenantId,
            'adfs',
            'https://app.example.com/sso/acs'
        );

        $this->assertSame('SAML', $result['protocol']);
        $this->assertNotEmpty($result['state']);
        $this->assertStringContainsString('SAMLRequest=', $result['authorization_url']);
        $this->assertStringContainsString('RelayState=', $result['authorization_url']);
    }
}
