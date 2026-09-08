<?php

namespace App\Modules\SaasAdmin\Controllers;

use App\Base\Controller;
use App\Modules\SaasAdmin\Services\SystemSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemSettingController extends Controller
{
    public function __construct(
        private readonly SystemSettingService $systemSettingService
    ) {
    }

    public function index(): JsonResponse
    {
        $settings = $this->systemSettingService->list();

        return response()->json([
            'status' => 'success',
            'data'   => $settings,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $setting = $this->systemSettingService->get($id);

        return response()->json([
            'status' => 'success',
            'data'   => $setting,
        ]);
    }

    public function upsert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'setting_key'   => 'required|string|max:150',
            'setting_value' => 'nullable|string',
            'description'   => 'nullable|string|max:500',
        ]);

        $actorId = $request->user()?->user_id ?? $request->user()?->admin_user_id ?? null;

        $setting = $this->systemSettingService->upsert(
            $validated['setting_key'],
            $validated['setting_value'] ?? null,
            $validated['description'] ?? null,
            $actorId
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'System setting saved successfully.',
            'data'    => $setting,
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $actorId = $request->user()?->user_id ?? $request->user()?->admin_user_id ?? null;
        $this->systemSettingService->softDelete($id, $actorId);

        return response()->json([
            'status'  => 'success',
            'message' => 'System setting deleted successfully.',
        ]);
    }
}
