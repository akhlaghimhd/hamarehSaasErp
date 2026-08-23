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
        $this->scopes = $scopes;
        $this->scopeIds = array_column($scopes, 'scope_id');
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
        return array_values(array_filter(
            $this->scopes,
            fn ($scope) => ($scope['scope_type'] ?? null) === $scopeType
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
        foreach ($this->scopes as $scope) {
            if (
                ($scope['scope_type'] ?? null) === $scopeType &&
                ($scope['reference_id'] ?? null) === $referenceId
            ) {
                return true;
            }
        }
        return false;
    }
}