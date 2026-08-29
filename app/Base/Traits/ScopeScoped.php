<?php

namespace App\Base\Traits;

use App\Base\Context\ScopeContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Context;

/**
 * ScopeScoped Trait
 *
 * Resource-level filter based on the current user's Scopes.
 * Complements TenantScoped and completes Law 4.2 chain:
 * User → Role → Permission → Scope → Resource
 *
 * Model usage:
 *
 *   class Branch extends Model
 *   {
 *       use TenantScoped, ScopeScoped;
 *
 *       protected static string $scopeType = 'BRANCH';
 *       protected static string $scopeColumn = 'branch_id';
 *   }
 *
 * Enforcement policy (F2 ADD — Scope Enforcement Policy):
 * - gradual (Policy B, default): no scopes of this scopeType → no extra filter
 *   (tenant isolation only). Supports gradual Scope assignment rollout.
 * - strict (Policy A): for types in config('scope.strict_scope_types'),
 *   missing scopes of that type → zero rows (fail-closed).
 *
 * Shared rules:
 * - User has scopes of this type → filter to allowed reference_ids.
 * - Scopes of type exist but reference_ids empty → zero rows.
 * - Without ScopeContext (Artisan/Queue): gradual skips filter;
 *   strict applies zero rows for listed types.
 *
 * Config: config/scope.php | env SCOPE_ENFORCEMENT_MODE=gradual|strict
 */
trait ScopeScoped
{
    /**
     * Boot the scope scoped trait for a model.
     */
    protected static function bootScopeScoped(): void
    {
        static::addGlobalScope('scope_isolation', function (Builder $builder) {
            $scopeType = static::getScopeType();
            $scopeColumn = static::getScopeColumn();

            if (empty($scopeType) || empty($scopeColumn)) {
                return;
            }

            $allowedReferenceIds = static::getAllowedReferenceIds($scopeType);

            // null = no scopes of this type
            if ($allowedReferenceIds === null) {
                if (static::isStrictModeFor($scopeType)) {
                    $builder->whereRaw('1 = 0');
                }
                // gradual: no extra filter (tenant isolation remains)
                return;
            }

            // empty list = scopes of type exist but no reference_id → deny all
            if (empty($allowedReferenceIds)) {
                $builder->whereRaw('1 = 0');
                return;
            }

            $table = $builder->getModel()->getTable();
            $builder->whereIn($table . '.' . $scopeColumn, $allowedReferenceIds);
        });
    }

    /**
     * Whether strict (Policy A) applies for this scopeType.
     */
    protected static function isStrictModeFor(string $scopeType): bool
    {
        $mode = config('scope.enforcement_mode', 'gradual');
        if ($mode !== 'strict') {
            return false;
        }

        $strictTypes = config('scope.strict_scope_types', ['COMPANY', 'BRANCH', 'WAREHOUSE']);

        return in_array(strtoupper($scopeType), array_map('strtoupper', $strictTypes), true);
    }

    /**
     * Scope type for this model (e.g. BRANCH, WAREHOUSE, COMPANY).
     */
    protected static function getScopeType(): ?string
    {
        return property_exists(static::class, 'scopeType')
            ? static::$scopeType
            : null;
    }

    /**
     * Column on the model table to filter by reference_id.
     */
    protected static function getScopeColumn(): ?string
    {
        if (property_exists(static::class, 'scopeColumn')) {
            return static::$scopeColumn;
        }

        $model = new static();
        return $model->getKeyName();
    }

    /**
     * Allowed reference_ids for the given scopeType.
     *
     * @return array|null  null = no scopes of this type defined for the user
     *                     array = allowed reference_ids (may be empty)
     */
    protected static function getAllowedReferenceIds(string $scopeType): ?array
    {
        $scopeContext = ScopeContext::getInstance();

        if (!$scopeContext->hasScopes()) {
            $scopesFromContext = Context::get('user_scopes');
            if (empty($scopesFromContext) && app()->bound('current_user_scopes')) {
                $scopesFromContext = app('current_user_scopes');
            }

            if (empty($scopesFromContext) || !is_array($scopesFromContext)) {
                return null;
            }

            $scopeContext->setScopes($scopesFromContext);
        }

        $scopesOfType = $scopeContext->getScopesByType($scopeType);

        if (empty($scopesOfType)) {
            return null;
        }

        return $scopeContext->getReferenceIdsByType($scopeType);
    }

    /**
     * Whether the current user may access a specific reference_id.
     */
    public static function currentUserHasAccessTo(string $referenceId): bool
    {
        $scopeType = static::getScopeType();
        if (empty($scopeType)) {
            return true;
        }

        $allowed = static::getAllowedReferenceIds($scopeType);

        if ($allowed === null) {
            // strict + no scopes of type = deny; gradual = allow (tenant only)
            return !static::isStrictModeFor($scopeType);
        }

        return in_array($referenceId, $allowed, true);
    }

    /**
     * Temporarily disable scope isolation for a query.
     */
    public static function withoutScopeIsolation(): Builder
    {
        return static::withoutGlobalScope('scope_isolation');
    }
}
