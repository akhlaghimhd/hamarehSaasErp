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
        $scope = (string) $request->query('scope', 'active');

        return response()->json(['data' => $this->cert->listCampaigns($tenantId, $scope)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(
            [
                'code'          => ['nullable', 'string', 'max:80'],
                'name'          => ['required', 'string', 'max:200'],
                'description'   => ['nullable', 'string', 'max:500'],
                'due_at'        => ['nullable', 'date'],
                'owner_user_id' => ['nullable', 'uuid'],
            ],
            [
                'name.required' => 'نام کمپین الزامی است.',
                'name.string'   => 'نام کمپین باید متن باشد.',
                'name.max'      => 'نام کمپین حداکثر ۲۰۰ کاراکتر است.',
                'code.string'   => 'کد کمپین باید متن باشد.',
                'code.max'      => 'کد کمپین حداکثر ۸۰ کاراکتر است.',
                'description.max' => 'توضیحات حداکثر ۵۰۰ کاراکتر است.',
                'due_at.date'   => 'تاریخ سررسید معتبر نیست.',
                'owner_user_id.uuid' => 'شناسه مالک معتبر نیست.',
            ],
            [
                'code'          => 'کد',
                'name'          => 'نام',
                'description'   => 'توضیحات',
                'due_at'        => 'تاریخ سررسید',
                'owner_user_id' => 'مالک',
            ]
        );

        $tenantId = $this->tenantId($request);
        $actor = optional($request->user())->user_id;

        $campaign = $this->cert->createCampaign(
            $tenantId,
            (string) ($data['code'] ?? ''),
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

    public function reEvaluate(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $actor = optional($request->user())->user_id;
        $result = $this->cert->reEvaluateCampaign($tenantId, $id, $actor);

        return response()->json(['data' => $result]);
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
        $data = $request->validate(
            [
                'decision' => ['required', 'string', 'in:APPROVED,REVOKE_REQUESTED,DEFERRED'],
                'note'     => ['nullable', 'string', 'max:500'],
            ],
            [
                'decision.required' => 'تصمیم الزامی است.',
                'decision.in'       => 'تصمیم انتخاب‌شده معتبر نیست.',
                'note.max'          => 'یادداشت حداکثر ۵۰۰ کاراکتر است.',
            ],
            [
                'decision' => 'تصمیم',
                'note'     => 'یادداشت',
            ]
        );

        $tenantId = $this->tenantId($request);
        $reviewer = optional($request->user())->user_id;
        if (! $reviewer) {
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

    public function archive(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $actor = optional($request->user())->user_id;
        $campaign = $this->cert->archiveCampaign($tenantId, $id, $actor);

        return response()->json([
            'data' => [
                'campaign_id' => $campaign->campaign_id,
                'archived'    => true,
            ],
        ]);
    }

    public function unarchive(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $actor = optional($request->user())->user_id;
        $campaign = $this->cert->unarchiveCampaign($tenantId, $id, $actor);

        return response()->json(['data' => $campaign]);
    }

    private function tenantId(Request $request): string
    {
        $tenantId = app()->bound('current_tenant_id') ? (string) app('current_tenant_id') : '';
        if ($tenantId === '') {
            $tenantId = (string) ($request->header('X-Tenant-Id') ?? '');
        }
        if ($tenantId === '') {
            throw new HttpException(400, 'شناسه سازمان (tenant) الزامی است.');
        }

        return $tenantId;
    }
}
