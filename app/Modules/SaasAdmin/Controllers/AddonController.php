<?php

namespace App\Modules\SaasAdmin\Controllers;

use App\Base\Controller;
use App\Modules\SaasAdmin\Requests\CreateAddonRequest;
use App\Modules\SaasAdmin\DTOs\CreateAddonDTO;
use App\Modules\SaasAdmin\Services\AddonService;
use Illuminate\Http\JsonResponse;

class AddonController extends Controller
{
    public function __construct(
        private readonly AddonService $addonService
    ) {
    }

    public function store(CreateAddonRequest $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized.',
            ], 401);
        }

        $dto = CreateAddonDTO::fromRequest($request->validated());
        $addon = $this->addonService->createAddon($dto->code, $dto->name, $user->user_id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Addon created successfully.',
            'data'    => $addon,
        ], 201);
    }

    public function index(): JsonResponse
    {
        $addons = $this->addonService->listActiveAddons();

        return response()->json([
            'status' => 'success',
            'data'   => $addons,
        ]);
    }
}