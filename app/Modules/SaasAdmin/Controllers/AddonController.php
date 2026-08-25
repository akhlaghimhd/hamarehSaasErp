<?php

namespace App\Modules\SaasAdmin\Controllers;

use App\Base\Controller;
use App\Modules\SaasAdmin\Requests\CreateAddonRequest;
use App\Modules\SaasAdmin\DTOs\CreateAddonDTO;
use App\Modules\SaasAdmin\Services\AddonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AddonController extends Controller
{
    public function __construct(
        private readonly AddonService $addonService
    ) {
    }

    public function store(CreateAddonRequest $request): JsonResponse
    {
        $userId = Auth::guard('api')->id();
        $dto = CreateAddonDTO::fromRequest($request->validated());

        $addon = $this->addonService->createAddon($dto->code, $dto->name, $userId);

        return response()->json([
            'message' => 'Addon created successfully.',
            'data'    => $addon,
        ], 201);
    }

    public function index(): JsonResponse
    {
        $addons = $this->addonService->listActiveAddons();

        return response()->json([
            'data' => $addons,
        ]);
    }
}