<?php

namespace App\Modules\IdentityCore\Controllers;

use App\Modules\IdentityCore\Services\TenantIdentitySettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Tenant-owned Identity policy settings (customer-controlled).
 *
 * Dual-approval toggles are NOT platform system_settings — they live in
 * tenant_settings via TenantIdentitySettingsService (SAASADM-P3 decision).
 * Temporary UI under /dashboard/identity/settings should later move to a
 * formal tenant System Settings page (FE residual).
 */
class IdentitySettingsController extends Controller
{
    public function __construct(
        private readonly TenantIdentitySettingsService $settings
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->settings->getIdentitySettings(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'require_role_assignment_approval'    => ['sometimes', 'boolean'],
            'require_privileged_access_approval'  => ['sometimes', 'boolean'],
        ]);

        $actor = optional($request->user())->user_id;
        if (!$actor) {
            throw new HttpException(401, 'احراز هویت الزامی است.');
        }

        $updated = $this->settings->updateIdentitySettings($data, null, (string) $actor);

        return response()->json([
            'data'    => $updated,
            'message' => 'تنظیمات هویت ذخیره شد.',
        ]);
    }
}
