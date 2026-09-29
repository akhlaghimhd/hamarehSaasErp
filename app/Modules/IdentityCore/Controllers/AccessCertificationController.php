<?php

namespace App\Modules\IdentityCore\Controllers;

use App\Modules\IdentityCore\Services\AccessCertificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AccessCertificationController extends Controller
{
    public function __construct(
        private readonly AccessCertificationService $cert
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);

        return response()->json(['data' => $this->cert->listCampaigns($tenantId)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'          => ['required', 'string', 'max:80'],
            'name'          => ['required', 'string', 'max:200'],
            'description'   => ['nullable', 'string', 'max:500'],
            'due_at'        => ['nullable', 'date'],
            'owner_user_id' => ['nullable', 'uuid'],
        ]);

        $tenantId = $this->tenantId($request);
        $actor = optional($request->user())->user_id;

        $campaign = $this->cert->createCampaign(
            $tenantId,
            $data['code'],
            $data['name'],
            $data['description'] ?? null,
            $data['due_at'] ?? null,
            $data['owner_user_id'] ?? null,
            $actor
        );

        return response()->json(['data' => $campaign], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId($request);

        return response()->json(['data' => $this->cert->campaignSummary($tenantId, $id)]);
    }

    public function open(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $actor = optional($request->user())->user_id;
        $campaign = $this->cert->openCampaign($tenantId, $id, $actor);

        return response()->json(['data' => $campaign]);
    }

    public function items(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $decision = $request->query('decision');

        return response()->json([
            'data' => $this->cert->listItems($tenantId, $id, $decision),
        ]);
    }

    public function certify(Request $request, string $itemId): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'string', 'in:APPROVED,REVOKE_REQUESTED,DEFERRED'],
            'note'     => ['nullable', 'string', 'max:500'],
        ]);

        $tenantId = $this->tenantId($request);
        $reviewer = optional($request->user())->user_id;
        if (!$reviewer) {
            throw new HttpException(401, 'کاربر احراز هویت نشده است.');
        }

        $item = $this->cert->certifyItem(
            $tenantId,
            $itemId,
            $data['decision'],
            $reviewer,
            $data['note'] ?? null
        );

        return response()->json(['data' => $item]);
    }

    public function complete(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $actor = optional($request->user())->user_id;
        $campaign = $this->cert->completeCampaign($tenantId, $id, $actor);

        return response()->json(['data' => $campaign]);
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
