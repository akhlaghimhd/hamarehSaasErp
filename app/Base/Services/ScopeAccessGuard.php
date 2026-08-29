<?php

namespace App\Base\Services;

use App\Base\Context\ScopeContext;
use RuntimeException;

/**
 * Central pre-action Scope validation (Layer 2 / F3).
 *
 * Law 4.2 flow: ... → Validate Scope → Execute Action
 * Aligns with F2 enforcement_mode (gradual | strict) in config/scope.php.
 *
 * Replaces duplicated private ensureScopeAccess() in module services.
 */
class ScopeAccessGuard
{
    public function __construct(
        protected ?ScopeContext $scopeContext = null
    ) {
        $this->scopeContext = $scopeContext ?? ScopeContext::getInstance();
    }

    /**
     * Assert the current user may act on the given scoped resource.
     *
     * @throws RuntimeException when access is denied
     */
    public function assertAccess(string $scopeType, string $referenceId): void
    {
        if (!$this->canAccess($scopeType, $referenceId)) {
            throw new RuntimeException('شما دسترسی لازم به این منبع را ندارید.');
        }
    }

    /**
     * Whether the current user may access the reference under current policy.
     */
    public function canAccess(string $scopeType, string $referenceId): bool
    {
        $scopesOfType = $this->scopeContext->getScopesByType($scopeType);
        $referenceIds = $this->scopeContext->getReferenceIdsByType($scopeType);

        // No scopes of this type at all
        if (empty($scopesOfType)) {
            // Policy A (strict): deny for configured types
            if ($this->isStrictModeFor($scopeType)) {
                return false;
            }

            // Policy B (gradual): allow (tenant isolation remains elsewhere)
            return true;
        }

        // Scopes of type exist but no usable reference_id, or target not listed
        if (empty($referenceIds) || !in_array($referenceId, $referenceIds, true)) {
            return false;
        }

        return true;
    }

    protected function isStrictModeFor(string $scopeType): bool
    {
        $mode = config('scope.enforcement_mode', 'gradual');
        if ($mode !== 'strict') {
            return false;
        }

        $strictTypes = config('scope.strict_scope_types', ['COMPANY', 'BRANCH', 'WAREHOUSE']);

        return in_array(strtoupper($scopeType), array_map('strtoupper', $strictTypes), true);
    }
}
