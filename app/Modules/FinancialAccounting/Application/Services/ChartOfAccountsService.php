<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Application\Services;

use App\Base\Context\TenantContext;
use App\Base\Exceptions\DomainException;
use App\Modules\FinancialAccounting\Infrastructure\Models\Account;
use App\Modules\FinancialAccounting\Infrastructure\Models\JournalItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * FIN-P0-10 — Chart of Accounts tree CRUD with soft-delete guards.
 * FIN-HOLD-W4 / ADR-FIN-003 L09 — CoA is per company (not tenant-shared).
 */
class ChartOfAccountsService
{
    public function __construct(
        private readonly FinanceCompanyScope $companyScope,
    ) {
    }

    public function listTree(string $companyId): Collection
    {
        $this->companyScope->assertCanReadCompany($companyId);

        $accounts = Account::query()
            ->where('company_id', $companyId)
            ->orderBy('account_code')
            ->get();

        return $this->buildTree($accounts);
    }

    public function listFlat(string $companyId): Collection
    {
        $this->companyScope->assertCanReadCompany($companyId);

        return Account::query()
            ->where('company_id', $companyId)
            ->orderBy('account_code')
            ->get();
    }

    public function find(string $accountId): Account
    {
        $account = Account::query()->where('account_id', $accountId)->firstOrFail();
        if ($account->company_id) {
            $this->companyScope->assertCanReadCompany((string) $account->company_id);
        }

        return $account;
    }

    /**
     * @param  array{
     *   company_id: string,
     *   account_code: string,
     *   name: string,
     *   account_type: int,
     *   normal_balance?: int,
     *   parent_account_id?: string|null,
     *   is_control_account?: bool,
     *   is_postable?: bool,
     *   account_level?: int,
     *   status?: int
     * }  $data
     */
    public function create(array $data): Account
    {
        $tenantId = $this->requireTenantId();

        $companyId = trim((string) ($data['company_id'] ?? ''));
        if ($companyId === '') {
            throw new DomainException('شناسه شرکت الزامی است.', 'fin.coa.company_required');
        }
        $this->companyScope->assertCanOperateCompany($companyId);

        $code = trim((string) ($data['account_code'] ?? ''));
        if ($code === '') {
            throw new DomainException('کد حساب الزامی است.', 'fin.coa.code_required');
        }

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new DomainException('نام حساب الزامی است.', 'fin.coa.name_required');
        }

        $this->assertCodeUnique($companyId, $code, null);
        $this->assertNameUnique($companyId, $name, null);

        $parentId = $data['parent_account_id'] ?? null;
        $level = (int) ($data['account_level'] ?? 1);

        if ($parentId) {
            $parent = Account::where('account_id', $parentId)
                ->where('company_id', $companyId)
                ->first();
            if (! $parent) {
                throw new DomainException('حساب والد یافت نشد.', 'fin.coa.parent_not_found');
            }
            $level = max($level, (int) $parent->account_level + 1);
        }

        $type = (int) ($data['account_type'] ?? 0);
        if (! in_array($type, [
            Account::TYPE_ASSET,
            Account::TYPE_LIABILITY,
            Account::TYPE_EQUITY,
            Account::TYPE_REVENUE,
            Account::TYPE_EXPENSE,
        ], true)) {
            throw new DomainException('نوع حساب نامعتبر است.', 'fin.coa.invalid_type');
        }

        return Account::create([
            'account_id'         => (string) Str::uuid(),
            'tenant_id'          => $tenantId,
            'company_id'         => $companyId,
            'parent_account_id'  => $parentId,
            'account_code'       => $code,
            'name'               => $name,
            'account_type'       => $type,
            'account_level'      => $level,
            'normal_balance'     => (int) ($data['normal_balance'] ?? Account::BALANCE_DEBIT),
            'is_control_account' => (bool) ($data['is_control_account'] ?? false),
            'is_postable'        => (bool) ($data['is_postable'] ?? true),
            'status'             => (int) ($data['status'] ?? 1),
            'row_version'        => 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(string $accountId, array $data): Account
    {
        $account = Account::query()->where('account_id', $accountId)->firstOrFail();
        $companyId = (string) $account->company_id;
        $this->companyScope->assertCanOperateCompany($companyId);

        if ($this->isRootAccount($account)) {
            throw new DomainException(
                'حساب کل ساختار ثابت سیستم است و قابل ویرایش نیست.',
                'fin.coa.root_locked'
            );
        }

        if (isset($data['account_code'])) {
            $code = trim((string) $data['account_code']);
            if ($code === '') {
                throw new DomainException('کد حساب الزامی است.', 'fin.coa.code_required');
            }
            if ($code !== $account->account_code) {
                if (JournalItem::where('account_id', $accountId)->exists()) {
                    throw new DomainException(
                        'کد حساب دارای گردش سند قابل تغییر نیست.',
                        'fin.coa.code_locked_has_movement'
                    );
                }
                $this->assertCodeUnique($companyId, $code, $accountId);
            }
            $account->account_code = $code;
        }

        if (array_key_exists('name', $data)) {
            $name = trim((string) $data['name']);
            if ($name === '') {
                throw new DomainException('نام حساب الزامی است.', 'fin.coa.name_required');
            }
            if ($name !== trim((string) $account->name)) {
                $this->assertNameUnique($companyId, $name, $accountId);
            }
            $account->name = $name;
        }
        if (array_key_exists('account_type', $data)) {
            $account->account_type = (int) $data['account_type'];
        }
        if (array_key_exists('normal_balance', $data)) {
            $account->normal_balance = (int) $data['normal_balance'];
        }
        if (array_key_exists('is_control_account', $data)) {
            $account->is_control_account = (bool) $data['is_control_account'];
        }
        if (array_key_exists('is_postable', $data)) {
            $account->is_postable = (bool) $data['is_postable'];
        }
        if (array_key_exists('status', $data)) {
            $account->status = (int) $data['status'];
        }
        if (array_key_exists('parent_account_id', $data)) {
            $parentId = $data['parent_account_id'];
            if ($parentId === $accountId) {
                throw new DomainException('حساب نمی‌تواند والد خودش باشد.', 'fin.coa.parent_cycle');
            }
            if ($parentId) {
                $parent = Account::where('account_id', $parentId)
                    ->where('company_id', $companyId)
                    ->first();
                if (! $parent) {
                    throw new DomainException('حساب والد یافت نشد.', 'fin.coa.parent_not_found');
                }
                $account->account_level = (int) $parent->account_level + 1;
            }
            $account->parent_account_id = $parentId;
        }

        $account->row_version = ((int) ($account->row_version ?? 1)) + 1;
        $account->save();

        return $account->fresh();
    }

    public function softDelete(string $accountId): void
    {
        $account = Account::query()->where('account_id', $accountId)->firstOrFail();
        $this->companyScope->assertCanOperateCompany((string) $account->company_id);

        if ($this->isRootAccount($account)) {
            throw new DomainException(
                'حساب کل ساختار ثابت سیستم است و قابل حذف نیست.',
                'fin.coa.root_locked'
            );
        }

        if (Account::where('parent_account_id', $accountId)->exists()) {
            throw new DomainException(
                'حساب دارای زیرمجموعه است و قابل حذف نیست.',
                'fin.coa.has_children'
            );
        }

        if (JournalItem::where('account_id', $accountId)->exists()) {
            throw new DomainException(
                'حساب دارای گردش است و قابل حذف نیست.',
                'fin.coa.has_movement'
            );
        }

        $account->row_version = ((int) ($account->row_version ?? 1)) + 1;
        $account->save();
        $account->delete();
    }

    /**
     * Clone full CoA tree from source company into target (for new subsidiary).
     * Does not copy journal history — only account structure.
     */
    public function cloneTreeToCompany(string $sourceCompanyId, string $targetCompanyId): int
    {
        $this->companyScope->assertCanOperateCompany($targetCompanyId);

        if (Account::query()->where('company_id', $targetCompanyId)->exists()) {
            throw new DomainException(
                'شرکت مقصد از قبل درخت حساب دارد.',
                'fin.coa.target_has_tree'
            );
        }

        $source = Account::query()
            ->where('company_id', $sourceCompanyId)
            ->orderBy('account_level')
            ->orderBy('account_code')
            ->get();

        if ($source->isEmpty()) {
            return 0;
        }

        $tenantId = $this->requireTenantId();
        $idMap = [];
        $count = 0;

        DB::transaction(function () use ($source, $targetCompanyId, $tenantId, &$idMap, &$count) {
            foreach ($source as $row) {
                $newId = (string) Str::uuid();
                $idMap[$row->account_id] = $newId;
                $parentNew = $row->parent_account_id
                    ? ($idMap[$row->parent_account_id] ?? null)
                    : null;

                Account::create([
                    'account_id'         => $newId,
                    'tenant_id'          => $tenantId,
                    'company_id'         => $targetCompanyId,
                    'parent_account_id'  => $parentNew,
                    'account_code'       => $row->account_code,
                    'name'               => $row->name,
                    'account_type'       => $row->account_type,
                    'account_level'      => $row->account_level,
                    'normal_balance'     => $row->normal_balance,
                    'is_control_account' => $row->is_control_account,
                    'is_postable'        => $row->is_postable,
                    'status'             => $row->status,
                    'row_version'        => 1,
                ]);
                $count++;
            }
        });

        return $count;
    }

    protected function isRootAccount(Account $account): bool
    {
        return $account->parent_account_id === null
            || (int) $account->account_level === 1;
    }

    protected function assertCodeUnique(string $companyId, string $code, ?string $exceptAccountId): void
    {
        $q = Account::query()
            ->where('company_id', $companyId)
            ->where('account_code', $code);
        if ($exceptAccountId) {
            $q->where('account_id', '!=', $exceptAccountId);
        }
        if ($q->exists()) {
            throw new DomainException(
                'کد حساب تکراری است؛ کد باید در تمام سطوح این شرکت یکتا باشد.',
                'fin.coa.code_duplicate'
            );
        }
    }

    protected function assertNameUnique(string $companyId, string $name, ?string $exceptAccountId): void
    {
        $normalized = mb_strtolower(trim($name), 'UTF-8');
        $q = Account::query()
            ->where('company_id', $companyId)
            ->whereRaw('lower(trim(name)) = ?', [$normalized]);
        if ($exceptAccountId) {
            $q->where('account_id', '!=', $exceptAccountId);
        }
        if ($q->exists()) {
            throw new DomainException(
                'نام حساب تکراری است؛ نام باید در تمام سطوح این شرکت یکتا باشد.',
                'fin.coa.name_duplicate'
            );
        }
    }

    /**
     * @param  Collection<int, Account>  $accounts
     * @return Collection<int, array<string, mixed>>
     */
    protected function buildTree(Collection $accounts, ?string $parentId = null): Collection
    {
        return $accounts
            ->filter(fn (Account $a) => $a->parent_account_id === $parentId)
            ->values()
            ->map(function (Account $a) use ($accounts) {
                return [
                    'account_id'         => $a->account_id,
                    'company_id'         => $a->company_id,
                    'account_code'       => $a->account_code,
                    'name'               => $a->name,
                    'account_type'       => $a->account_type,
                    'account_level'      => $a->account_level,
                    'normal_balance'     => $a->normal_balance,
                    'is_control_account' => $a->is_control_account,
                    'is_postable'        => $a->is_postable,
                    'status'             => $a->status,
                    'children'           => $this->buildTree($accounts, $a->account_id)->all(),
                ];
            });
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
