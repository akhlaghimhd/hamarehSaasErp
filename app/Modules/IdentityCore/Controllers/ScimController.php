<?php

namespace App\Modules\IdentityCore\Controllers;

use App\Base\Context\TenantContext;
use App\Modules\IdentityCore\Services\ScimUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * ID-W3-05 residual — SCIM 2.0 Users HTTP surface.
 * Auth: same tenant middleware + permission identity.user.* (bearer client credentials deferred).
 */
class ScimController
{
    public function __construct(
        private readonly ScimUserService $scim
    ) {
    }

    public function serviceProviderConfig(): JsonResponse
    {
        return response()->json($this->scim->serviceProviderConfig());
    }

    public function indexUsers(Request $request): JsonResponse
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        try {
            $payload = $this->scim->listUsers(
                $tenantId,
                (int) $request->query('startIndex', 1),
                (int) $request->query('count', 50),
                $request->query('filter')
            );

            return response()->json($payload);
        } catch (HttpException $e) {
            return $this->scimError($e->getStatusCode(), $e->getMessage());
        }
    }

    public function showUser(string $id): JsonResponse
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        try {
            return response()->json($this->scim->getUser($tenantId, $id));
        } catch (HttpException $e) {
            return $this->scimError($e->getStatusCode(), $e->getMessage());
        }
    }

    public function storeUser(Request $request): JsonResponse
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        try {
            $resource = $this->scim->createUser($tenantId, $request->all());

            return response()->json($resource, 201);
        } catch (HttpException $e) {
            return $this->scimError($e->getStatusCode(), $e->getMessage());
        }
    }

    public function replaceUser(Request $request, string $id): JsonResponse
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        try {
            return response()->json($this->scim->replaceUser($tenantId, $id, $request->all()));
        } catch (HttpException $e) {
            return $this->scimError($e->getStatusCode(), $e->getMessage());
        }
    }

    public function destroyUser(string $id): Response|JsonResponse
    {
        $tenantId = TenantContext::getInstance()->getTenantId();

        try {
            $this->scim->deleteUser($tenantId, $id);

            return response()->noContent();
        } catch (HttpException $e) {
            return $this->scimError($e->getStatusCode(), $e->getMessage());
        }
    }

    private function scimError(int $status, string $detail): JsonResponse
    {
        return response()->json([
            'schemas' => [ScimUserService::SCHEMA_ERROR],
            'status'  => (string) $status,
            'detail'  => $detail,
        ], $status);
    }
}
