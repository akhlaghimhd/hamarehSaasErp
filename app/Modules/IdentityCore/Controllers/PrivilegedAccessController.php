<?php

namespace App\Modules\IdentityCore\Controllers;

use App\Modules\IdentityCore\Services\PrivilegedAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PrivilegedAccessController extends Controller
{
    public function __construct(
        private readonly PrivilegedAccessService $service
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);

        return response()->json([
            'data' => $this->service->listGrants($tenantId, $request->query('status')),
        ]);
    }

    public function request(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id'           => ['required', 'uuid'],
            'tenant_role_id'    => ['required', 'uuid'],
            'reason'            => ['required', 'string', 'min:5', 'max:500'],
            'duration_minutes'  => ['nullable', 'integer', 'min:5', 'max:43200'],
        ]);

        $tenantId = $this->tenantId($request);
        $actor = optional($request->user())->user_id;

        $grant = $this->service->requestGrant(
            $tenantId,
            $data['user_id'],
            $data['tenant_role_id'],
            $data['reason'],
            (int) ($data['duration_minutes'] ?? 60),
            $actor
        );

        return response()->json(['data' => $grant], 201);
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $actor = optional($request->user())->user_id;
        if (!$actor) {
            throw new HttpException(401, 'کاربر احراز هویت نشده است.');
        }

        $grant = $this->service->approveAndActivate($tenantId, $id, $actor);

        return response()->json(['data' => $grant]);
    }

    public function deny(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $tenantId = $this->tenantId($request);
        $actor = optional($request->user())->user_id;
        if (!$actor) {
            throw new HttpException(401, 'کاربر احراز هویت نشده است.');
        }

        $grant = $this->service->deny($tenantId, $id, $actor, $data['note'] ?? null);

        return response()->json(['data' => $grant]);
    }

    public function revoke(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $tenantId = $this->tenantId($request);
        $actor = optional($request->user())->user_id;
        if (!$actor) {
            throw new HttpException(401, 'کاربر احراز هویت نشده است.');
        }

        $grant = $this->service->revoke($tenantId, $id, $actor, $data['reason'] ?? null);

        return response()->json(['data' => $grant]);
    }

    public function extend(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:43200'],
        ]);

        $tenantId = $this->tenantId($request);
        $actor = optional($request->user())->user_id;
        if (!$actor) {
            throw new HttpException(401, 'کاربر احراز هویت نشده است.');
        }

        $grant = $this->service->extend(
            $tenantId,
            $id,
            (int) $data['duration_minutes'],
            $actor
        );

        return response()->json(['data' => $grant]);
    }

    public function reactivate(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:43200'],
            'tenant_role_id'   => ['nullable', 'uuid'],
            'reason'           => ['nullable', 'string', 'min:5', 'max:500'],
        ]);

        $tenantId = $this->tenantId($request);
        $actor = optional($request->user())->user_id;
        if (!$actor) {
            throw new HttpException(401, 'کاربر احراز هویت نشده است.');
        }

        $grant = $this->service->reactivate(
            $tenantId,
            $id,
            (int) $data['duration_minutes'],
            $actor,
            $data['tenant_role_id'] ?? null,
            $data['reason'] ?? null
        );

        return response()->json(['data' => $grant]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'tenant_role_id'   => ['nullable', 'uuid'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:43200'],
            'reason'           => ['nullable', 'string', 'min:5', 'max:500'],
        ]);

        $tenantId = $this->tenantId($request);
        $actor = optional($request->user())->user_id;
        if (!$actor) {
            throw new HttpException(401, 'کاربر احراز هویت نشده است.');
        }

        $grant = $this->service->updateGrant(
            $tenantId,
            $id,
            $actor,
            $data['tenant_role_id'] ?? null,
            isset($data['duration_minutes']) ? (int) $data['duration_minutes'] : null,
            $data['reason'] ?? null
        );

        return response()->json(['data' => $grant]);
    }

    public function markRole(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_role_id' => ['required', 'uuid'],
            'is_privileged'  => ['required', 'boolean'],
        ]);

        $tenantId = $this->tenantId($request);
        $role = $this->service->markRolePrivileged(
            $tenantId,
            $data['tenant_role_id'],
            (bool) $data['is_privileged']
        );

        return response()->json(['data' => $role]);
    }

    private function tenantId(Request $request): string
    {
        $tenantId = app()->bound('current_tenant_id') ? (string) app('current_tenant_id') : '';
        if ($tenantId === '') {
            $tenantId = (string) ($request->header('X-Tenant-Id') ?? '');
        }
        if ($tenantId === '') {
            throw new HttpException(400, 'tenant context الزامی است.');
        }

        return $tenantId;
    }
}
