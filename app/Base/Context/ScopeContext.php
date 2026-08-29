<?php

namespace App\Base\Context;

class ScopeContext
{
    protected static ?ScopeContext $instance = null;

    /** @var array<string> لیست scope_idهای کاربر جاری */
    protected array $scopeIds = [];

    /** @var array<array> لیست کامل Scopeها (شامل type و reference_id) */
    protected array $scopes = [];

    /** @var string|null tenant_user_id کاربر جاری */
    protected ?string $tenantUserId = null;

    /**
     * Get the singleton instance of ScopeContext
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Reset the singleton instance (useful for testing)
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * تنظیم Scopeهای کاربر جاری
     *
     * @param array $scopes آرایه‌ای از رکوردهای Scope (باید شامل scope_id, scope_type, reference_id باشد)
     * @param string|null $tenantUserId
     */
    public function setScopes(array $scopes, ?string $tenantUserId = null): self
    {
        // Normalize scope_type to uppercase at ingestion
        $this->scopes = array_map(function ($scope) {
            if (is_array($scope) && isset($scope['scope_type'])) {
                $scope['scope_type'] = strtoupper((string) $scope['scope_type']);
            }
            return $scope;
        }, $scopes);

        $this->scopeIds = array_values(array_filter(array_column($this->scopes, 'scope_id')));
        $this->tenantUserId = $tenantUserId;

        return $this;
    }

    /**
     * دریافت لیست scope_idها
     */
    public function getScopeIds(): array
    {
        return $this->scopeIds;
    }

    /**
     * دریافت لیست کامل Scopeها
     */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    /**
     * دریافت tenant_user_id کاربر جاری
     */
    public function getTenantUserId(): ?string
    {
        return $this->tenantUserId;
    }

    /**
     * بررسی اینکه کاربر حداقل یک Scope دارد یا نه
     */
    public function hasScopes(): bool
    {
        return !empty($this->scopeIds);
    }

    /**
     * دریافت Scopeهای یک نوع خاص (مثلاً BRANCH یا WAREHOUSE)
     */
    public function getScopesByType(string $scopeType): array
    {
        $type = strtoupper($scopeType);

        return array_values(array_filter(
            $this->scopes,
            fn ($scope) => strtoupper((string) ($scope['scope_type'] ?? '')) === $type
        ));
    }

    /**
     * دریافت reference_idهای یک نوع خاص
     */
    public function getReferenceIdsByType(string $scopeType): array
    {
        $filtered = $this->getScopesByType($scopeType);

        return array_values(array_filter(array_column($filtered, 'reference_id')));
    }

    /**
     * بررسی اینکه کاربر به یک reference_id خاص دسترسی دارد یا نه
     */
    public function hasAccessTo(string $scopeType, string $referenceId): bool
    {
        $type = strtoupper($scopeType);

        foreach ($this->scopes as $scope) {
            if (
                strtoupper((string) ($scope['scope_type'] ?? '')) === $type &&
                ($scope['reference_id'] ?? null) === $referenceId
            ) {
                return true;
            }
        }

        return false;
    }
}
