<?php

namespace App\Modules\SaasAdmin\Http\Middleware;

use App\Modules\SaasAdmin\Models\AdminUser;
use App\Modules\SaasAdmin\Models\AdminUserSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates Bearer token against admin_user_sessions (not Sanctum personal tokens).
 */
class AuthenticateAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json([
                'status'  => 'error',
                'message' => 'احراز هویت ادمین لازم است.',
            ], 401);
        }

        $hash = hash('sha256', $token);
        $session = AdminUserSession::query()
            ->where('token_hash', $hash)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->first();

        if (!$session) {
            return response()->json([
                'status'  => 'error',
                'message' => 'نشست ادمین نامعتبر یا منقضی است.',
            ], 401);
        }

        $admin = AdminUser::query()
            ->where('admin_user_id', $session->admin_user_id)
            ->whereNull('deleted_at')
            ->where('status', 1)
            ->first();

        if (!$admin) {
            return response()->json([
                'status'  => 'error',
                'message' => 'کاربر ادمین یافت نشد یا غیرفعال است.',
            ], 401);
        }

        $session->last_activity_at = now();
        $session->save();

        $request->attributes->set('admin_user', $admin);
        $request->attributes->set('admin_session', $session);
        $request->setUserResolver(static fn () => $admin);

        return $next($request);
    }
}
