<?php

namespace App\Modules\IdentityCore\Services;

use App\Modules\IdentityCore\Models\TenantSsoProvider;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\UserSsoIdentity;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * ID-W1-04 — OIDC SSO foundation.
 *
 * Live token exchange is used when $claimsOverride is null.
 * Tests inject validated claims via completeLoginWithClaims to avoid external IdP.
 */
class SsoService
{
    private const STATE_TTL = 600;

    public function __construct(
        private readonly AuthenticationService $auth,
    ) {
    }

    /**
     * @return list<array{sso_provider_id:string,code:string,name:string,protocol:string}>
     */
    public function listEnabledProviders(string $tenantId): array
    {
        return TenantSsoProvider::query()
            ->where('tenant_id', $tenantId)
            ->where('is_enabled', true)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['sso_provider_id', 'code', 'name', 'protocol'])
            ->map(fn ($p) => [
                'sso_provider_id' => $p->sso_provider_id,
                'code'            => $p->code,
                'name'            => $p->name,
                'protocol'        => $p->protocol,
            ])
            ->values()
            ->all();
    }

    public function upsertProvider(string $tenantId, array $data, ?string $actorId = null): TenantSsoProvider
    {
        $code = strtolower(trim((string) ($data['code'] ?? '')));
        if ($code === '' || !preg_match('/^[a-z0-9_\-]{2,80}$/', $code)) {
            throw new HttpException(422, 'کد ارائه‌دهنده SSO نامعتبر است.');
        }

        $provider = TenantSsoProvider::query()
            ->where('tenant_id', $tenantId)
            ->where('code', $code)
            ->whereNull('deleted_at')
            ->first();

        if (!$provider) {
            $provider = new TenantSsoProvider([
                'sso_provider_id' => (string) Str::uuid(),
                'tenant_id'       => $tenantId,
                'code'            => $code,
            ]);
        }

        $provider->name = (string) ($data['name'] ?? $provider->name ?? $code);
        $provider->protocol = strtoupper((string) ($data['protocol'] ?? 'OIDC'));
        if ($provider->protocol !== 'OIDC') {
            throw new HttpException(422, 'در این نسخه فقط پروتکل OIDC پشتیبانی می‌شود.');
        }
        $provider->issuer = (string) ($data['issuer'] ?? '');
        $provider->authorization_endpoint = (string) ($data['authorization_endpoint'] ?? '');
        $provider->token_endpoint = (string) ($data['token_endpoint'] ?? '');
        $provider->jwks_uri = $data['jwks_uri'] ?? null;
        $provider->client_id = (string) ($data['client_id'] ?? '');
        $provider->scopes = (string) ($data['scopes'] ?? 'openid profile email');
        $provider->is_enabled = (bool) ($data['is_enabled'] ?? true);
        $provider->auto_provision = (bool) ($data['auto_provision'] ?? false);
        $provider->updated_by = $actorId;
        if (!$provider->exists) {
            $provider->created_by = $actorId;
        }

        if (array_key_exists('client_secret', $data) && $data['client_secret'] !== null && $data['client_secret'] !== '') {
            $provider->setClientSecret((string) $data['client_secret']);
        }

        if ($provider->issuer === '' || $provider->authorization_endpoint === '' || $provider->token_endpoint === '' || $provider->client_id === '') {
            throw new HttpException(422, 'issuer، authorization_endpoint، token_endpoint و client_id الزامی هستند.');
        }

        $provider->save();

        return $provider->fresh();
    }

    /**
     * @return array{authorization_url:string,state:string}
     */
    public function beginAuthorization(string $tenantId, string $providerCode, string $redirectUri): array
    {
        $provider = $this->findEnabledProvider($tenantId, $providerCode);

        $state = Str::random(40);
        Cache::put($this->stateKey($state), [
            'tenant_id'     => $tenantId,
            'provider_code' => $provider->code,
            'provider_id'   => $provider->sso_provider_id,
            'redirect_uri'  => $redirectUri,
            'created_at'    => now()->toIso8601String(),
        ], self::STATE_TTL);

        $query = http_build_query([
            'response_type' => 'code',
            'client_id'     => $provider->client_id,
            'redirect_uri'  => $redirectUri,
            'scope'         => $provider->scopes,
            'state'         => $state,
        ]);

        return [
            'authorization_url' => rtrim($provider->authorization_endpoint, '?').'?'.$query,
            'state'             => $state,
        ];
    }

    /**
     * Live OIDC code exchange + claim login.
     *
     * @return array auth session payload from AuthenticationService
     */
    public function handleCallback(string $code, string $state): array
    {
        $ctx = Cache::pull($this->stateKey($state));
        if (!$ctx || empty($ctx['tenant_id']) || empty($ctx['provider_code'])) {
            throw new HttpException(400, 'state نامعتبر یا منقضی است.');
        }

        $provider = $this->findEnabledProvider($ctx['tenant_id'], $ctx['provider_code']);
        $claims = $this->exchangeCodeForClaims($provider, $code, (string) $ctx['redirect_uri']);

        return $this->completeLoginWithClaims(
            $ctx['tenant_id'],
            $provider,
            $claims
        );
    }

    /**
     * Core login path (testable without live IdP).
     *
     * @param  array{sub:string,email?:string,email_verified?:bool}  $claims
     * @return array
     */
    public function completeLoginWithClaims(string $tenantId, TenantSsoProvider $provider, array $claims): array
    {
        $sub = trim((string) ($claims['sub'] ?? ''));
        if ($sub === '') {
            throw new HttpException(401, 'ادعاهای هویت (sub) از IdP دریافت نشد.');
        }

        $email = isset($claims['email']) ? strtolower(trim((string) $claims['email'])) : null;

        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$tenantId]);

        $identity = UserSsoIdentity::query()
            ->where('tenant_id', $tenantId)
            ->where('sso_provider_id', $provider->sso_provider_id)
            ->where('external_subject', $sub)
            ->whereNull('deleted_at')
            ->first();

        $user = null;
        if ($identity) {
            $user = User::query()->where('user_id', $identity->user_id)->whereNull('deleted_at')->first();
        }

        if (!$user && $email) {
            $user = User::query()->where('email', $email)->whereNull('deleted_at')->first();
            if ($user) {
                $this->linkIdentity($user->user_id, $tenantId, $provider, $sub, $email);
            }
        }

        if (!$user) {
            if (!$provider->auto_provision) {
                throw new HttpException(
                    403,
                    'کاربری متناظر با این هویت سازمانی یافت نشد. ابتدا کاربر باید در سیستم عضو شود یا auto_provision فعال باشد.'
                );
            }
            // Auto-provision is deferred to a controlled path; foundation blocks without membership.
            throw new HttpException(
                403,
                'auto_provision در foundation فقط پس از تعریف سیاست عضویت فعال می‌شود. کاربر باید از قبل عضو tenant باشد.'
            );
        }

        if ((int) $user->status !== 1) {
            throw new HttpException(403, 'حساب کاربری غیرفعال است.');
        }

        // Ensure link exists and touch last_login
        $identity = $this->linkIdentity($user->user_id, $tenantId, $provider, $sub, $email);
        $identity->last_login_at = now();
        $identity->save();

        // MFA still applies for SSO sessions
        $user->load('credential');
        if (app(MfaService::class)->isRequired($user)) {
            return $this->auth->issueMfaChallengeSession($user, $tenantId);
        }

        return $this->auth->completeLoginForUser($user, $tenantId);
    }

    private function linkIdentity(
        string $userId,
        string $tenantId,
        TenantSsoProvider $provider,
        string $sub,
        ?string $email
    ): UserSsoIdentity {
        $identity = UserSsoIdentity::query()
            ->where('tenant_id', $tenantId)
            ->where('sso_provider_id', $provider->sso_provider_id)
            ->where('external_subject', $sub)
            ->whereNull('deleted_at')
            ->first();

        if ($identity) {
            if ($email) {
                $identity->external_email = $email;
                $identity->save();
            }

            return $identity;
        }

        return UserSsoIdentity::create([
            'sso_identity_id'  => (string) Str::uuid(),
            'user_id'          => $userId,
            'tenant_id'        => $tenantId,
            'sso_provider_id'  => $provider->sso_provider_id,
            'provider_code'    => $provider->code,
            'external_subject' => $sub,
            'external_email'   => $email,
            'row_version'      => 1,
        ]);
    }

    /**
     * @return array{sub:string,email?:string}
     */
    private function exchangeCodeForClaims(TenantSsoProvider $provider, string $code, string $redirectUri): array
    {
        $payload = [
            'grant_type'   => 'authorization_code',
            'code'         => $code,
            'redirect_uri' => $redirectUri,
            'client_id'    => $provider->client_id,
        ];
        $secret = $provider->getClientSecret();
        if ($secret !== null) {
            $payload['client_secret'] = $secret;
        }

        $response = Http::asForm()
            ->timeout(15)
            ->post($provider->token_endpoint, $payload);

        if (!$response->successful()) {
            throw new HttpException(401, 'دریافت توکن از IdP ناموفق بود.');
        }

        $body = $response->json() ?? [];
        $idToken = $body['id_token'] ?? null;
        if (!$idToken || !is_string($idToken)) {
            throw new HttpException(401, 'id_token در پاسخ IdP وجود ندارد.');
        }

        return $this->decodeIdTokenClaims($idToken, $provider);
    }

    /**
     * Foundation: decode JWT payload and validate iss/aud/exp.
     * Full JWKS signature verification is Wave-2 hardening (ID-W3 / Adaptive).
     *
     * @return array{sub:string,email?:string}
     */
    private function decodeIdTokenClaims(string $jwt, TenantSsoProvider $provider): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) < 2) {
            throw new HttpException(401, 'فرمت id_token نامعتبر است.');
        }

        $payload = json_decode($this->base64UrlDecode($parts[1]), true);
        if (!is_array($payload)) {
            throw new HttpException(401, 'payload توکن قابل خواندن نیست.');
        }

        if (($payload['iss'] ?? null) !== $provider->issuer) {
            throw new HttpException(401, 'issuer توکن با پیکربندی هم‌خوانی ندارد.');
        }

        $aud = $payload['aud'] ?? null;
        $audOk = is_string($aud)
            ? $aud === $provider->client_id
            : (is_array($aud) && in_array($provider->client_id, $aud, true));
        if (!$audOk) {
            throw new HttpException(401, 'audience توکن نامعتبر است.');
        }

        $exp = (int) ($payload['exp'] ?? 0);
        if ($exp > 0 && $exp < time() - 30) {
            throw new HttpException(401, 'توکن منقضی شده است.');
        }

        $sub = (string) ($payload['sub'] ?? '');
        if ($sub === '') {
            throw new HttpException(401, 'sub در توکن وجود ندارد.');
        }

        $out = ['sub' => $sub];
        if (!empty($payload['email'])) {
            $out['email'] = (string) $payload['email'];
        }

        return $out;
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return (string) base64_decode(strtr($data, '-_', '+/'));
    }

    private function findEnabledProvider(string $tenantId, string $code): TenantSsoProvider
    {
        $provider = TenantSsoProvider::query()
            ->where('tenant_id', $tenantId)
            ->where('code', $code)
            ->where('is_enabled', true)
            ->whereNull('deleted_at')
            ->first();

        if (!$provider) {
            throw new HttpException(404, 'ارائه‌دهنده SSO فعال یافت نشد.');
        }

        return $provider;
    }

    private function stateKey(string $state): string
    {
        return 'sso_oidc_state:'.$state;
    }
}
