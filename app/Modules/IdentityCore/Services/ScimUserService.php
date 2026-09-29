<?php

namespace App\Modules\IdentityCore\Services;

use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Models\UserCredential;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * ID-W3-05 — SCIM 2.0 Users foundation (RFC 7643 / 7644 subset).
 *
 * Maps enterprise IdP provisioning to tenant-scoped memberships.
 * Authn for /scim endpoints is deferred (bearer token / OAuth client credentials).
 */
class ScimUserService
{
    public const SCHEMA_USER = 'urn:ietf:params:scim:schemas:core:2.0:User';
    public const SCHEMA_LIST = 'urn:ietf:params:scim:api:messages:2.0:ListResponse';
    public const SCHEMA_ERROR = 'urn:ietf:params:scim:api:messages:2.0:Error';

    /**
     * SCIM ServiceProviderConfig (minimal).
     *
     * @return array<string, mixed>
     */
    public function serviceProviderConfig(): array
    {
        return [
            'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:ServiceProviderConfig'],
            'patch' => ['supported' => true],
            'bulk' => ['supported' => false, 'maxOperations' => 0, 'maxPayloadSize' => 0],
            'filter' => ['supported' => true, 'maxResults' => 100],
            'changePassword' => ['supported' => false],
            'sort' => ['supported' => false],
            'etag' => ['supported' => false],
            'authenticationSchemes' => [
                [
                    'type' => 'oauthbearertoken',
                    'name' => 'OAuth Bearer Token',
                    'description' => 'Authentication via OAuth 2.0 Bearer Token (tenant-scoped).',
                    'primary' => true,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function listUsers(string $tenantId, int $startIndex = 1, int $count = 50, ?string $filter = null): array
    {
        $startIndex = max(1, $startIndex);
        $count = max(1, min($count, 100));

        $query = TenantUser::query()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->with('user');

        if ($filter) {
            $this->applyFilter($query, $filter);
        }

        $total = (clone $query)->count();
        $rows = $query->orderBy('created_at')
            ->skip($startIndex - 1)
            ->take($count)
            ->get();

        $resources = $rows->map(fn (TenantUser $tu) => $this->toScimUser($tu))->values()->all();

        return [
            'schemas'      => [self::SCHEMA_LIST],
            'totalResults' => $total,
            'startIndex'   => $startIndex,
            'itemsPerPage' => count($resources),
            'Resources'    => $resources,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getUser(string $tenantId, string $id): array
    {
        $tu = $this->findMembership($tenantId, $id);

        return $this->toScimUser($tu);
    }

    /**
     * Create or reactivate membership from SCIM User payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createUser(string $tenantId, array $payload): array
    {
        $userName = $payload['userName'] ?? null;
        if (!$userName || !is_string($userName)) {
            throw new HttpException(400, 'userName is required');
        }

        $email = $this->extractPrimaryEmail($payload) ?? (str_contains($userName, '@') ? $userName : null);
        $mobile = $this->extractPhone($payload);
        $active = array_key_exists('active', $payload) ? (bool) $payload['active'] : true;
        $given = $payload['name']['givenName'] ?? null;
        $family = $payload['name']['familyName'] ?? null;

        return DB::transaction(function () use ($tenantId, $userName, $email, $mobile, $active, $given, $family) {
            $user = null;
            if ($email) {
                $user = User::query()->where('email', $email)->whereNull('deleted_at')->first();
            }
            if (!$user && $mobile) {
                $user = User::query()->where('mobile', $mobile)->whereNull('deleted_at')->first();
            }

            if (!$user) {
                $user = User::create([
                    'user_id'    => (string) Str::uuid(),
                    'email'      => $email,
                    'mobile'     => $mobile,
                    'first_name' => $given,
                    'last_name'  => $family,
                    'user_kind'  => 1,
                    'status'     => 1,
                ]);

                UserCredential::create([
                    'credential_id'       => (string) Str::uuid(),
                    'user_id'             => $user->user_id,
                    'password_hash'       => Hash::make(Str::random(32)),
                    'must_set_password'   => true,
                    'authentication_type' => 1,
                    'is_verified'         => false,
                    'two_factor_enabled'  => false,
                    'failed_login_count'  => 0,
                ]);
            }

            $tu = TenantUser::withTrashed()
                ->where('tenant_id', $tenantId)
                ->where('user_id', $user->user_id)
                ->first();

            if ($tu) {
                if ($tu->trashed()) {
                    $tu->restore();
                }
                $tu->update([
                    'status'     => $active ? 1 : 0,
                    'updated_at' => now(),
                ]);
            } else {
                $tu = TenantUser::create([
                    'tenant_user_id' => (string) Str::uuid(),
                    'tenant_id'      => $tenantId,
                    'user_id'        => $user->user_id,
                    'status'         => $active ? 1 : 0,
                    'is_owner'       => false,
                ]);
            }

            $tu->load('user');

            return $this->toScimUser($tu);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function replaceUser(string $tenantId, string $id, array $payload): array
    {
        $tu = $this->findMembership($tenantId, $id);
        $user = $tu->user;
        if (!$user) {
            throw new HttpException(404, 'User not found');
        }

        $updates = [];
        if (isset($payload['name']['givenName'])) {
            $updates['first_name'] = $payload['name']['givenName'];
        }
        if (isset($payload['name']['familyName'])) {
            $updates['last_name'] = $payload['name']['familyName'];
        }
        $email = $this->extractPrimaryEmail($payload);
        if ($email) {
            $updates['email'] = $email;
        }
        $mobile = $this->extractPhone($payload);
        if ($mobile) {
            $updates['mobile'] = $mobile;
        }

        if ($updates !== []) {
            $user->update($updates);
        }

        if (array_key_exists('active', $payload)) {
            $tu->update(['status' => $payload['active'] ? 1 : 0]);
        }

        $tu->load('user');

        return $this->toScimUser($tu->fresh(['user']));
    }

    /**
     * Soft-deactivate membership (SCIM DELETE).
     */
    public function deleteUser(string $tenantId, string $id): void
    {
        $tu = $this->findMembership($tenantId, $id);
        $tu->update(['status' => 0]);
        $tu->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function toScimUser(TenantUser $tu): array
    {
        $user = $tu->user;

        return [
            'schemas' => [self::SCHEMA_USER],
            'id' => $tu->tenant_user_id,
            'externalId' => $user?->user_id,
            'userName' => $user?->email ?? $user?->mobile ?? $tu->tenant_user_id,
            'name' => [
                'givenName'  => $user?->first_name,
                'familyName' => $user?->last_name,
                'formatted'  => trim(($user?->first_name ?? '').' '.($user?->last_name ?? '')) ?: null,
            ],
            'emails' => $user?->email ? [
                ['value' => $user->email, 'type' => 'work', 'primary' => true],
            ] : [],
            'phoneNumbers' => $user?->mobile ? [
                ['value' => $user->mobile, 'type' => 'mobile', 'primary' => true],
            ] : [],
            'active' => (int) $tu->status === 1 && $tu->deleted_at === null,
            'meta' => [
                'resourceType' => 'User',
                'created' => optional($tu->created_at)?->toIso8601String(),
                'lastModified' => optional($tu->updated_at)?->toIso8601String(),
                'location' => '/scim/v2/Users/'.$tu->tenant_user_id,
            ],
        ];
    }

    private function findMembership(string $tenantId, string $id): TenantUser
    {
        $tu = TenantUser::withTrashed()
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($id) {
                $q->where('tenant_user_id', $id)
                    ->orWhere('user_id', $id);
            })
            ->with('user')
            ->first();

        if (!$tu || $tu->trashed()) {
            throw new HttpException(404, 'Resource not found');
        }

        return $tu;
    }

    private function applyFilter($query, string $filter): void
    {
        // Minimal: userName eq "x" / emails eq "x"
        if (preg_match('/userName\s+eq\s+"([^"]+)"/i', $filter, $m)) {
            $query->whereHas('user', fn ($q) => $q->where('email', $m[1])->orWhere('mobile', $m[1]));

            return;
        }
        if (preg_match('/emails\.value\s+eq\s+"([^"]+)"/i', $filter, $m)
            || preg_match('/emails\s+eq\s+"([^"]+)"/i', $filter, $m)) {
            $query->whereHas('user', fn ($q) => $q->where('email', $m[1]));
        }
    }

    private function extractPrimaryEmail(array $payload): ?string
    {
        $emails = $payload['emails'] ?? [];
        if (!is_array($emails) || $emails === []) {
            return null;
        }
        foreach ($emails as $e) {
            if (!empty($e['primary']) && !empty($e['value'])) {
                return (string) $e['value'];
            }
        }
        $first = $emails[0]['value'] ?? null;

        return $first ? (string) $first : null;
    }

    private function extractPhone(array $payload): ?string
    {
        $phones = $payload['phoneNumbers'] ?? [];
        if (!is_array($phones) || $phones === []) {
            return null;
        }
        foreach ($phones as $p) {
            if (!empty($p['primary']) && !empty($p['value'])) {
                return (string) $p['value'];
            }
        }
        $first = $phones[0]['value'] ?? null;

        return $first ? (string) $first : null;
    }
}
