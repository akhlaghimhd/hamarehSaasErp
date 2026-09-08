<?php

namespace App\Modules\SaasAdmin\Controllers;

use App\Base\Controller;
use App\Modules\SaasAdmin\Services\AdminAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class AdminAuthController extends Controller
{
    public function __construct(
        private readonly AdminAuthService $adminAuthService
    ) {
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => 'required|string|max:100',
            'password' => 'required|string',
        ]);

        try {
            $result = $this->adminAuthService->login(
                $validated['username'],
                $validated['password'],
                $request->ip() ?? '0.0.0.0',
                $request->userAgent()
            );
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 401);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Login successful.',
            'data'    => [
                'admin_user' => $result['admin_user'],
                'session_id' => $result['session']->session_id,
                'token'      => $result['token'],
                'expires_at' => $result['session']->expires_at,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->bearerToken() ?? $request->input('token');
        if (!$token) {
            return response()->json(['status' => 'error', 'message' => 'Token required.'], 422);
        }

        $this->adminAuthService->logout($token);

        return response()->json(['status' => 'success', 'message' => 'Logged out.']);
    }
}
