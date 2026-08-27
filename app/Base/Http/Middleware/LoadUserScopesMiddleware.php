<?php

namespace App\Base\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Context;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Symfony\Component\HttpFoundation\Response;

class LoadUserScopesMiddleware
{
    /**
     * بارگذاری کامل Security Context کاربر احراز هویت‌شده
     * طبق قانون ۴.۴: user_id, tenant_id, roles, scopes (+ permissions)
     *
     * این middleware باید بعد از auth:sanctum و TenantContextMiddleware اجرا شود.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $tenantId = TenantContext::getInstance()->getTenantId();

        // اگر کاربر لاگین نباشد یا TenantContext تنظیم نشده باشد، چیزی بارگذاری نمی‌کنیم
        if (!$user || !$tenantId) {
            return $next($request);
        }

        // پیدا کردن tenant_user مربوط به این کاربر در مستأجر جاری
        $tenantUser = DB::table('tenant_users')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->user_id)
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->first();

        if (!$tenantUser) {
            // کاربر عضو این مستأجر نیست (نباید به اینجا برسد چون TenantContextMiddleware چک کرده)
            return $next($request);
        }

        // -------------------------------------------------
        // 1. بارگذاری Scopes فعال کاربر
        // -------------------------------------------------
        $scopes = DB::table('tenant_user_scopes')
            ->join('tenant_scopes', 'tenant_user_scopes.scope_id', '=', 'tenant_scopes.scope_id')
            ->where('tenant_user_scopes.tenant_id', $tenantId)
            ->where('tenant_user_scopes.tenant_user_id', $tenantUser->tenant_user_id)
            ->whereNull('tenant_user_scopes.deleted_at')
            ->whereNull('tenant_scopes.deleted_at')
            ->where('tenant_scopes.is_active', true)
            ->select([
                'tenant_scopes.scope_id',
                'tenant_scopes.scope_name',
                'tenant_scopes.scope_type',
                'tenant_scopes.reference_id',
                'tenant_scopes.description',
            ])
            ->get()
            ->map(fn ($item) => (array) $item)
            ->toArray();

        // -------------------------------------------------
        // 2. بارگذاری Roles کاربر
        // -------------------------------------------------
        $roleRows = DB::table('tenant_user_roles')
            ->join('tenant_roles', 'tenant_user_roles.tenant_role_id', '=', 'tenant_roles.tenant_role_id')
            ->where('tenant_user_roles.tenant_id', $tenantId)
            ->where('tenant_user_roles.user_id', $user->user_id)
            ->whereNull('tenant_user_roles.deleted_at')
            ->whereNull('tenant_roles.deleted_at')
            ->where('tenant_roles.status', 1)
            ->select([
                'tenant_roles.tenant_role_id',
                'tenant_roles.code',
                'tenant_roles.name',
                'tenant_roles.role_type',
            ])
            ->get();

        $roles = $roleRows->map(function ($role) {
            return [
                'role_id'   => $role->tenant_role_id,
                'code'      => $role->code,
                'name'      => $role->name,
                'role_type' => $role->role_type,
            ];
        })->values()->toArray();

        $roleIds = $roleRows->pluck('tenant_role_id')->unique()->values()->toArray();

        // -------------------------------------------------
        // 3. بارگذاری Permissions از طریق Roles
        // -------------------------------------------------
        $permissions = [];
        if (!empty($roleIds)) {
            $permissions = DB::table('tenant_role_permissions')
                ->join('tenant_permissions', 'tenant_role_permissions.tenant_permission_id', '=', 'tenant_permissions.tenant_permission_id')
                ->where('tenant_role_permissions.tenant_id', $tenantId)
                ->whereIn('tenant_role_permissions.tenant_role_id', $roleIds)
                ->whereNull('tenant_role_permissions.deleted_at')
                ->whereNull('tenant_permissions.deleted_at')
                ->where('tenant_permissions.status', 1)
                ->pluck('tenant_permissions.code')
                ->unique()
                ->values()
                ->toArray();
        }

        // -------------------------------------------------
        // 4. تنظیم ScopeContext
        // -------------------------------------------------
        ScopeContext::getInstance()->setScopes($scopes, $tenantUser->tenant_user_id);

        // -------------------------------------------------
        // 5. تنظیم کامل Security Context در Laravel Context و Container
        //    مطابق قانون ۴.۴
        // -------------------------------------------------
        $securityContext = [
            'user_id'        => $user->user_id,
            'tenant_id'      => $tenantId,
            'tenant_user_id' => $tenantUser->tenant_user_id,
            'roles'          => $roles,
            'permissions'    => $permissions,
            'scopes'         => $scopes,
            'is_owner'       => (bool) ($tenantUser->is_owner ?? false),
        ];

        Context::add('user_scopes', $scopes);
        Context::add('tenant_user_id', $tenantUser->tenant_user_id);
        Context::add('user_roles', $roles);
        Context::add('user_permissions', $permissions);
        Context::add('security_context', $securityContext);

        app()->instance('current_tenant_user_id', $tenantUser->tenant_user_id);
        app()->instance('current_user_scopes', $scopes);
        app()->instance('current_user_roles', $roles);
        app()->instance('current_user_permissions', $permissions);
        app()->instance('current_security_context', $securityContext);

        return $next($request);
    }

    /**
     * پاکسازی پس از پایان درخواست
     */
    public function terminate($request, $response): void
    {
        ScopeContext::resetInstance();
    }
}