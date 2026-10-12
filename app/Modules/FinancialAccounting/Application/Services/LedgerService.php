<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Application\Services;

use App\Base\Context\TenantContext;
use App\Base\Exceptions\DomainException;
use App\Modules\FinancialAccounting\Infrastructure\Models\Ledger;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * ADR-FIN-002 FIN-CSN-F2-01 — ledgers per company, gated by FinanceCompanyScope.
 */
class LedgerService
{
    public function __construct(
        protected FinanceCompanyScope $companyScope = new FinanceCompanyScope()
    ) {
    }

    /**
     * @return Collection<int, Ledger>
     */
    public function list(?string $companyId = null): Collection
    {
        $q = Ledger::query()->orderBy('code');
        $this->companyScope->applyToQuery($q, $companyId !== '' ? $companyId : null);

        return $q->limit(200)->get();
    }

    public function find(string $ledgerId): Ledger
    {
        $ledger = Ledger::query()->where('ledger_id', $ledgerId)->first();
        if (! $ledger) {
            throw new DomainException('دفتر کل یافت نشد.', 'fin.ledger.not_found');
        }
        $this->companyScope->assertCanAccessCompany((string) $ledger->company_id);

        return $ledger;
    }

    /**
     * @param  array{company_id: string, code: string, name: string, is_leading?: bool, base_currency_id?: string|null}  $data
     */
    public function create(array $data): Ledger
    {
        $tenantId = $this->requireTenantId();
        $companyId = (string) ($data['company_id'] ?? '');
        $this->companyScope->assertCanAccessCompany($companyId);

        $code = trim((string) ($data['code'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));
        if ($code === '' || $name === '') {
            throw new DomainException('کد و نام دفتر کل الزامی است.', 'fin.ledger.code_name_required');
        }

        $isLeading = (bool) ($data['is_leading'] ?? false);
        if ($isLeading) {
            Ledger::query()
                ->where('company_id', $companyId)
                ->where('is_leading', true)
                ->update(['is_leading' => false]);
        }

        return Ledger::create([
            'ledger_id'         => (string) Str::uuid(),
            'tenant_id'         => $tenantId,
            'company_id'        => $companyId,
            'code'              => $code,
            'name'              => $name,
            'is_leading'        => $isLeading,
            'base_currency_id'  => $data['base_currency_id'] ?? null,
            'status'            => 1,
            'row_version'       => 1,
        ]);
    }

    /** Ensure a leading ledger exists for company (idempotent helper for onboarding). */
    public function ensureLeading(string $companyId, string $code = 'LG', string $name = 'Leading'): Ledger
    {
        $this->companyScope->assertCanAccessCompany($companyId);

        $existing = Ledger::query()
            ->where('company_id', $companyId)
            ->where('is_leading', true)
            ->first();

        if ($existing) {
            return $existing;
        }

        $any = Ledger::query()->where('company_id', $companyId)->orderBy('created_at')->first();
        if ($any) {
            $any->is_leading = true;
            $any->row_version = ((int) ($any->row_version ?? 1)) + 1;
            $any->save();

            return $any->fresh();
        }

        return $this->create([
            'company_id' => $companyId,
            'code'       => $code,
            'name'       => $name,
            'is_leading' => true,
        ]);
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
