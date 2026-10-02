<?php

namespace App\Modules\IdentityCore\Controllers;

use App\Modules\IdentityCore\Services\TenantIdentitySettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Tenant system settings for Identity policies (customer-controlled, not feature packs).
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
            'require_role_assignment_approval' => ['sometimes', 'boolean'],
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
