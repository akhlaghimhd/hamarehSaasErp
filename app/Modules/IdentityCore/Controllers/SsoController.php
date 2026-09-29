<?php

namespace App\Modules\IdentityCore\Controllers;

use App\Modules\IdentityCore\Services\SsoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SsoController extends Controller
{
    public function __construct(
        private readonly SsoService $sso,
    ) {
    }

    public function providers(Request $request): JsonResponse
    {
        $tenantId = (string) ($request->header('X-Tenant-Id') ?? $request->query('tenant_id') ?? '');
        if ($tenantId === '') {
            return response()->json(['message' => 'tenant_id الزامی است.'], 422);
        }

        return response()->json([
            'data' => $this->sso->listEnabledProviders($tenantId),
        ]);
    }

    public function begin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_id'    => ['required', 'uuid'],
            'provider'     => ['required', 'string', 'max:80'],
            'redirect_uri' => ['required', 'url'],
        ]);

        $result = $this->sso->beginAuthorization(
            $data['tenant_id'],
            $data['provider'],
            $data['redirect_uri']
        );

        return response()->json($result);
    }

    public function callback(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'  => ['required', 'string'],
            'state' => ['required', 'string'],
        ]);

        $session = $this->sso->handleCallback($data['code'], $data['state']);

        return response()->json($session);
    }

    public function upsertProvider(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'                    => ['required', 'string', 'max:80'],
            'name'                    => ['required', 'string', 'max:200'],
            'protocol'                => ['nullable', 'string', 'max:20'],
            'issuer'                  => ['required', 'string', 'max:500'],
            'authorization_endpoint'  => ['required', 'url'],
            'token_endpoint'          => ['required', 'url'],
            'jwks_uri'                => ['nullable', 'url'],
            'client_id'               => ['required', 'string', 'max:300'],
            'client_secret'           => ['nullable', 'string', 'max:500'],
            'scopes'                  => ['nullable', 'string', 'max:300'],
            'is_enabled'              => ['nullable', 'boolean'],
            'auto_provision'          => ['nullable', 'boolean'],
        ]);

        $tenantId = (string) ($request->attributes->get('tenant_id') ?? $request->header('X-Tenant-Id') ?? '');
        if ($tenantId === '') {
            return response()->json(['message' => 'tenant context الزامی است.'], 422);
        }

        $actorId = optional($request->user())->user_id;
        $provider = $this->sso->upsertProvider($tenantId, $data, $actorId);

        return response()->json([
            'data' => [
                'sso_provider_id' => $provider->sso_provider_id,
                'code'            => $provider->code,
                'name'            => $provider->name,
                'protocol'        => $provider->protocol,
                'is_enabled'      => $provider->is_enabled,
                'auto_provision'  => $provider->auto_provision,
            ],
        ], 201);
    }
}
