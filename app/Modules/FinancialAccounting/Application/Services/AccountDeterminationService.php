<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Application\Services;

use App\Base\Context\TenantContext;
use App\Base\Exceptions\DomainException;
use App\Modules\FinancialAccounting\Infrastructure\Models\AccountDeterminationRule;
use Illuminate\Support\Str;

/** FIN-P3-02 — Deterministic account mapping by event_type + line_role. */
class AccountDeterminationService
{
    public function upsertRule(array $data): AccountDeterminationRule
    {
        $tenantId = $this->requireTenantId();

        return AccountDeterminationRule::create([
            'rule_id'     => (string) Str::uuid(),
            'tenant_id'   => $tenantId,
            'event_type'  => $data['event_type'],
            'line_role'   => $data['line_role'],
            'account_id'  => $data['account_id'],
            'company_id'  => $data['company_id'] ?? null,
            'priority'    => $data['priority'] ?? 100,
            'is_active'   => $data['is_active'] ?? true,
            'description' => $data['description'] ?? null,
            'row_version' => 1,
        ]);
    }

    /**
     * @return array{account_id: string, reason: string, rule_id: string|null}
     */
    public function resolve(string $eventType, string $lineRole, ?string $companyId = null): array
    {
        $q = AccountDeterminationRule::query()
            ->where('event_type', $eventType)
            ->where('line_role', $lineRole)
            ->where('is_active', true)
            ->orderBy('priority');

        $rules = $q->get();

        $rule = $rules->first(function (AccountDeterminationRule $r) use ($companyId) {
            return $r->company_id === null || ($companyId && $r->company_id === $companyId);
        });

        // Prefer company-specific over tenant-wide when both match priority order
        $companyRule = $companyId
            ? $rules->first(fn (AccountDeterminationRule $r) => $r->company_id === $companyId)
            : null;
        if ($companyRule) {
            $rule = $companyRule;
        }

        if (! $rule) {
            throw new DomainException(
                "قاعده تعیین حساب برای {$eventType}/{$lineRole} یافت نشد.",
                'fin.det.rule_missing'
            );
        }

        return [
            'account_id' => (string) $rule->account_id,
            'reason'     => $rule->description
                ?: "قاعده {$rule->event_type}/{$rule->line_role} (اولویت {$rule->priority})",
            'rule_id'    => (string) $rule->rule_id,
        ];
    }

    protected function requireTenantId(): string
    {
        $tenantId = TenantContext::getInstance()->getTenantId();
        if (! $tenantId) {
            throw new DomainException('بافت مستأجر تنظیم نشده است.', 'fin.tenant_missing');
        }

        return $tenantId;
    }
}
