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
 * ID-W1-03/04 residual — OIDC full path + SAML AuthnRequest foundation.
 *
 * SAML ACS assertion parsing / signature validation is deferred (hardening).
 * beginAuthorization works for both OIDC and SAML Redirect binding.
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

        $protocol = strtoupper((string) ($data['protocol'] ?? 'OIDC'));
        if (!in_array($protocol, ['OIDC', 'SAML'], true)) {
            throw new HttpException(422, 'پروتکل باید OIDC یا SAML باشد.');
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
        $provider->protocol = $protocol;
        $provider->issuer = (string) ($data['issuer'] ?? '');
        $provider->authorization_endpoint = (string) ($data['authorization_endpoint'] ?? '');
        $provider->token_endpoint = (string) ($data['token_endpoint'] ?? ($protocol === 'SAML' ? 'SAML' : ''));
        $provider->jwks_uri = $data['jwks_uri'] ?? null;
        $provider->client_id = (string) ($data['client_id'] ?? ($protocol === 'SAML' ? $provider->issuer : ''));
        $provider->scopes = (string) ($data['scopes'] ?? ($protocol === 'SAML' ? 'SAML' : 'openid profile email'));
        $provider->is_enabled = (bool) ($data['is_enabled'] ?? true);
        $provider->auto_provision = (bool) ($data['auto_provision'] ?? false);
        $provider->updated_by = $actorId;
        if (!$provider->exists) {
            $provider->created_by = $actorId;
        }

        if (array_key_exists('client_secret', $data) && $data['client_secret'] !== null && $data['client_secret'] !== '') {
            $provider->setClientSecret((string) $data['client_secret']);
        }

        if ($protocol === 'OIDC') {
            if ($provider->issuer === '' || $provider->authorization_endpoint === '' || $provider->token_endpoint === '' || $provider->client_id === '') {
                throw new HttpException(422, 'issuer، authorization_endpoint، token_endpoint و client_id الزامی هستند.');
            }
        } else {
            // SAML: issuer = EntityID of SP or IdP entity; authorization_endpoint = IdP SSO URL
            if ($provider->issuer === '' || $provider->authorization_endpoint === '') {
                throw new HttpException(422, 'برای SAML فیلدهای issuer (EntityID) و authorization_endpoint (SSO URL) الزامی هستند.');
            }
            if ($provider->client_id === '') {
                $provider->client_id = $provider->issuer;
            }
            if ($provider->token_endpoint === '') {
                $provider->token_endpoint = 'SAML';
            }
        }

        $provider->save();

        return $provider->fresh();
    }

    /**
     * @return array{authorization_url:string,state:string,protocol:string}
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
            'protocol'      => $provider->protocol,
            'created_at'    => now()->toIso8601String(),
        ], self::STATE_TTL);

        if (strtoupper($provider->protocol) === 'SAML') {
            $url = $this->buildSamlRedirectUrl($provider, $redirectUri, $state);

            return [
                'authorization_url' => $url,
                'state'             => $state,
                'protocol'          => 'SAML',
            ];
        }

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
            'protocol'          => 'OIDC',
        ];
    }

    /**
     * Minimal SAML 2.0 AuthnRequest (Redirect binding, deflate+base64).
     */
    private function buildSamlRedirectUrl(TenantSsoProvider $provider, string $acsUrl, string $relayState): string
    {
        $id = '_'.Str::uuid()->toString();
        $instant = gmdate('Y-m-d\TH:i:s\Z');
        $issuer = htmlspecialchars($provider->client_id ?: $provider->issuer, ENT_XML1);
        $dest = htmlspecialchars($provider->authorization_endpoint, ENT_XML1);
        $acs = htmlspecialchars($acsUrl, ENT_XML1);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<samlp:AuthnRequest xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" '
            .'xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" '
            .'ID="'.$id.'" Version="2.0" IssueInstant="'.$instant.'" '
            .'Destination="'.$dest.'" '
            .'AssertionConsumerServiceURL="'.$acs.'" '
            .'ProtocolBinding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST">'
            .'<saml:Issuer>'.$issuer.'</saml:Issuer>'
            .'</samlp:AuthnRequest>';

        $deflated = gzdeflate($xml);
        $encoded = base64_encode($deflated ?: $xml);

        $query = http_build_query([
            'SAMLRequest' => $encoded,
            'RelayState'  => $relayState,
        ]);

        $sep = str_contains($provider->authorization_endpoint, '?') ? '&' : '?';

        return $provider->authorization_endpoint.$sep.$query;
    }

    public function handleCallback(string $code, string $state): array
    {
        $ctx = Cache::pull($this->stateKey($state));
        if (!$ctx || empty($ctx['tenant_id']) || empty($ctx['provider_code'])) {
            throw new HttpException(400, 'state نامعتبر یا منقضی است.');
        }

        $protocol = strtoupper((string) ($ctx['protocol'] ?? 'OIDC'));
        if ($protocol === 'SAML') {
            throw new HttpException(
                501,
                'ACS و اعتبارسنجی Assertion برای SAML در این نسخه تکمیل نشده است. از completeLoginWithClaims پس از اعتبارسنجی IdP استفاده کنید یا OIDC را پیکربندی کنید.'
            );
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
     * @param  array{sub:string,email?:string,email_verified?:bool}  $claims
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
            throw new HttpException(
                403,
                'auto_provision در foundation فقط پس از تعریف سیاست عضویت فعال می‌شود. کاربر باید از قبل عضو tenant باشد.'
            );
        }

        if ((int) $user->status !== 1) {
            throw new HttpException(403, 'حساب کاربری غیرفعال است.');
        }

        $identity = $this->linkIdentity($user->user_id, $tenantId, $provider, $sub, $email);
        $identity->last_login_at = now();
        $identity->save();

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
