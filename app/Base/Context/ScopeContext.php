<?php

namespace App\Base\Context;

class ScopeContext
{
    protected static ?ScopeContext $instance = null;

    /** @var array<string> */
    protected array $scopeIds = [];

    /** @var array<array> */
    protected array $scopes = [];

    protected ?string $tenantUserId = null;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * @param array $scopes records with scope_id, scope_type, reference_id and optional reference_ids
     */
    public function setScopes(array $scopes, ?string $tenantUserId = null): self
    {
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

    public function getScopeIds(): array
    {
        return $this->scopeIds;
    }

    public function getScopes(): array
    {
        return $this->scopes;
    }

    public function getTenantUserId(): ?string
    {
        return $this->tenantUserId;
    }

    public function hasScopes(): bool
    {
        return !empty($this->scopeIds);
    }

    public function getScopesByType(string $scopeType): array
    {
        $type = strtoupper($scopeType);

        return array_values(array_filter(
            $this->scopes,
            fn ($scope) => strtoupper((string) ($scope['scope_type'] ?? '')) === $type
        ));
    }

    public function getReferenceIdsByType(string $scopeType): array
    {
        $filtered = $this->getScopesByType($scopeType);
        $ids = [];

        foreach ($filtered as $scope) {
            $multi = $scope['reference_ids'] ?? null;
            if (is_array($multi) && $multi !== []) {
                foreach ($multi as $rid) {
                    if ($rid !== null && $rid !== '') {
                        $ids[] = (string) $rid;
                    }
                }
                continue;
            }
            if (!empty($scope['reference_id'])) {
                $ids[] = (string) $scope['reference_id'];
            }
        }

        return array_values(array_unique($ids));
    }

    public function hasAccessTo(string $scopeType, string $referenceId): bool
    {
        $type = strtoupper($scopeType);
        $want = (string) $referenceId;

        foreach ($this->scopes as $scope) {
            if (strtoupper((string) ($scope['scope_type'] ?? '')) !== $type) {
                continue;
            }
            $multi = $scope['reference_ids'] ?? null;
            if (is_array($multi) && $multi !== []) {
                foreach ($multi as $rid) {
                    if ((string) $rid === $want) {
                        return true;
                    }
                }
                continue;
            }
            if (($scope['reference_id'] ?? null) !== null && (string) $scope['reference_id'] === $want) {
                return true;
            }
        }

        return false;
    }
}
