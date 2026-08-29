<?php

namespace App\Base\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Base\Context\ScopeContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * F3 — Validate Scope on actions (route-level).
 *
 * Usage in routes:
 *   middleware('scope:BRANCH,branch')
 *   middleware('scope:BRANCH,branch_id')
 *
 * Parameters:
 *   $scopeType  — e.g. BRANCH, COMPANY, WAREHOUSE (must match model $scopeType)
 *   $routeParam — route parameter name that holds the reference_id
 *
 * Behaviour (same policy as ScopeScoped / F2):
 * - gradual + user has no scopes of this type → allow (tenant isolation only)
 * - strict  + user has no scopes of this type → 403
 * - user has scopes of this type → reference_id must be in allowed list
 * - empty reference_ids for the type → 403
 *
 * Must run after tenant.context + auth:sanctum + load.scopes.
 */
class RequireScope
{
    public function handle(Request $request, Closure $next, string $scopeType, string $routeParam = 'id'): Response
    {
        $scopeType = strtoupper(trim($scopeType));
        $referenceId = $request->route($routeParam);

        if ($referenceId === null || $referenceId === '') {
            // Also try common nested patterns (e.g. model binding object)
            $bound = $request->route($routeParam);
            if (is_object($bound) && method_exists($bound, 'getKey')) {
                $referenceId = (string) $bound->getKey();
            }
        }

        if ($referenceId === null || $referenceId === '') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Scope reference parameter is missing from the route.',
            ], 400);
        }

        $referenceId = (string) $referenceId;

        if (!$this->userMayAccess($scopeType, $referenceId)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'شما مجوز Scope برای دسترسی به این منبع را ندارید.',
            ], 403);
        }

        return $next($request);
    }

    /**
     * Mirrors ScopeScoped::currentUserHasAccessTo policy without requiring a model class.
     */
    protected function userMayAccess(string $scopeType, string $referenceId): bool
    {
        $scopeContext = ScopeContext::getInstance();

        $scopesOfType = $scopeContext->getScopesByType($scopeType);

        // null-equivalent: no scopes of this type assigned
        if (empty($scopesOfType)) {
            $mode = config('scope.enforcement_mode', 'gradual');
            if ($mode !== 'strict') {
                return true; // gradual: allow (tenant isolation remains)
            }

            $strictTypes = config('scope.strict_scope_types', ['COMPANY', 'BRANCH', 'WAREHOUSE']);
            $isStrictType = in_array($scopeType, array_map('strtoupper', $strictTypes), true);

            return !$isStrictType; // strict + listed type → deny; unlisted type → allow
        }

        $allowedIds = $scopeContext->getReferenceIdsByType($scopeType);

        if (empty($allowedIds)) {
            return false;
        }

        return in_array($referenceId, $allowedIds, true);
    }
}
