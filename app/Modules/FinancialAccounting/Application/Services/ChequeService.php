<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Application\Services;

use App\Base\Context\TenantContext;
use App\Base\Exceptions\DomainException;
use App\Modules\FinancialAccounting\Infrastructure\Models\Cheque;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** FIN-P1-07 + ADR-FIN-002 + FIN-HOLD-W1 — Cheque lifecycle; list=read, create/transition=operate. */
class ChequeService
{
    public function __construct(
        protected FinanceCompanyScope $companyScope = new FinanceCompanyScope()
    ) {
    }

    /**
     * @param  array{company_id?: string|null, status?: string|null, limit?: int}  $filters
     * @return Collection<int, Cheque>
     */
    public function list(array $filters = []): Collection
    {
        $q = Cheque::query()->orderByDesc('due_date');

        $companyFilter = isset($filters['company_id']) && $filters['company_id'] !== ''
            ? (string) $filters['company_id']
            : null;
        $this->companyScope->applyToQuery($q, $companyFilter);

        if (! empty($filters['status'])) {
            $q->where('status', (string) $filters['status']);
        }

        $limit = min(200, max(1, (int) ($filters['limit'] ?? 200)));

        return $q->limit($limit)->get();
    }

    public function create(array $data): Cheque
    {
        $tenantId = $this->requireTenantId();
        $companyId = (string) ($data['company_id'] ?? '');
        $this->companyScope->assertCanOperateCompany($companyId);

        $direction = (string) ($data['direction'] ?? '');
        if (! in_array($direction, [Cheque::DIR_IN, Cheque::DIR_OUT], true)) {
            throw new DomainException('جهت چک نامعتبر است.', 'fin.cheque.direction');
        }

        $status = $direction === Cheque::DIR_IN
            ? Cheque::STATUS_RECEIVED
            : Cheque::STATUS_ISSUED;

        return Cheque::create([
            'cheque_id'       => (string) Str::uuid(),
            'tenant_id'       => $tenantId,
            'company_id'      => $companyId,
            'direction'       => $direction,
            'cheque_number'   => (string) $data['cheque_number'],
            'bank_name'       => $data['bank_name'] ?? null,
            'issue_date'      => $data['issue_date'] ?? null,
            'due_date'        => $data['due_date'],
            'amount'          => $data['amount'],
            'currency_id'     => $data['currency_id'] ?? null,
            'payee_name'      => $data['payee_name'] ?? null,
            'drawer_name'     => $data['drawer_name'] ?? null,
            'status'          => $status,
            'cash_account_id' => $data['cash_account_id'] ?? null,
            'description'     => $data['description'] ?? null,
            'row_version'     => 1,
        ]);
    }

    public function transition(string $chequeId, string $toStatus): Cheque
    {
        $cheque = Cheque::where('cheque_id', $chequeId)->firstOrFail();
        $this->companyScope->assertCanOperateCompany((string) $cheque->company_id);

        $from = (string) $cheque->status;
        $allowed = Cheque::TRANSITIONS[$from] ?? [];

        if (! in_array($toStatus, $allowed, true)) {
            throw new DomainException(
                "انتقال وضعیت چک از {$from} به {$toStatus} مجاز نیست.",
                'fin.cheque.invalid_transition'
            );
        }

        $cheque->status = $toStatus;
        $cheque->row_version = ((int) ($cheque->row_version ?? 1)) + 1;
        $cheque->save();

        return $cheque->fresh();
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
