<?php

namespace App\Modules\SaasAdmin\Http\Middleware;

use App\Modules\SaasAdmin\Models\AdminUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class RequireAdminPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        /** @var AdminUser|null $admin */
        $admin = $request->attributes->get('admin_user');

        if (!$admin instanceof AdminUser) {
            return response()->json([
                'status'  => 'error',
                'message' => 'احراز هویت ادمین لازم است.',
            ], 401);
        }

        $codes = $this->permissionCodesFor($admin->admin_user_id);

        if (! in_array($permission, $codes, true)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'شما مجوز دسترسی به این بخش ادمین را ندارید.',
            ], 403);
        }

        return $next($request);
    }

    /**
     * @return list<string>
     */
    private function permissionCodesFor(string $adminUserId): array
    {
        return DB::table('admin_user_roles as ur')
            ->join('admin_role_permissions as rp', function ($j) {
                $j->on('rp.admin_role_id', '=', 'ur.admin_role_id')
                    ->whereNull('rp.deleted_at');
            })
            ->join('admin_permissions as p', function ($j) {
                $j->on('p.admin_permission_id', '=', 'rp.admin_permission_id')
                    ->whereNull('p.deleted_at');
            })
            ->where('ur.admin_user_id', $adminUserId)
            ->whereNull('ur.deleted_at')
            ->pluck('p.code')
            ->unique()
            ->values()
            ->all();
    }
}
