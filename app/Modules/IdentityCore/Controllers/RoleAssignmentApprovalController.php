<?php

namespace App\Modules\IdentityCore\Controllers;

use App\Modules\IdentityCore\Services\RoleAssignmentApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * ID-W3-02 — HTTP surface for role assignment request / approve / reject.
 */
class RoleAssignmentApprovalController extends Controller
{
    public function __construct(
        private readonly RoleAssignmentApprovalService $service
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);

        return response()->json([
            'data' => $this->service->listPending($tenantId)->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id'        => ['required', 'uuid'],
            'tenant_role_id' => ['required', 'uuid'],
            'reason'         => ['nullable', 'string', 'max:500'],
            'valid_from'     => ['nullable', 'date'],
            'valid_to'       => ['nullable', 'date', 'after_or_equal:valid_from'],
        ]);

        $tenantId = $this->tenantId($request);
        $actor = optional($request->user())->user_id;
        if (!$actor) {
            throw new HttpException(401, 'احراز هویت الزامی است.');
        }

        $req = $this->service->requestAssignment(
            $tenantId,
            $data['user_id'],
            $data['tenant_role_id'],
            $actor,
            $data['reason'] ?? null,
            $data['valid_from'] ?? null,
            $data['valid_to'] ?? null
        );

        return response()->json(['data' => $req], 201);
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'review_note' => ['nullable', 'string', 'max:500'],
        ]);

        $tenantId = $this->tenantId($request);
        $actor = optional($request->user())->user_id;
        if (!$actor) {
            throw new HttpException(401, 'احراز هویت الزامی است.');
        }

        $req = $this->service->approve(
            $tenantId,
            $id,
            $actor,
            $data['review_note'] ?? null
        );

        return response()->json(['data' => $req]);
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'review_note' => ['nullable', 'string', 'max:500'],
        ]);

        $tenantId = $this->tenantId($request);
        $actor = optional($request->user())->user_id;
        if (!$actor) {
            throw new HttpException(401, 'احراز هویت الزامی است.');
        }

        $req = $this->service->reject(
            $tenantId,
            $id,
            $actor,
            $data['review_note'] ?? null
        );

        return response()->json(['data' => $req]);
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
