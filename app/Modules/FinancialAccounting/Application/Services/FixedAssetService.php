<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Application\Services;

use App\Base\Context\TenantContext;
use App\Base\Exceptions\DomainException;
use App\Modules\FinancialAccounting\Infrastructure\Models\DepreciationRun;
use App\Modules\FinancialAccounting\Infrastructure\Models\DepreciationRunLine;
use App\Modules\FinancialAccounting\Infrastructure\Models\FixedAsset;
use App\Modules\FinancialAccounting\Infrastructure\Models\Ledger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * FIN-P4-03 — Fixed assets + depreciation run → journal DRAFT only (never auto-post).
 */
class FixedAssetService
{
    public function __construct(
        protected JournalEntryService $journals = new JournalEntryService()
    ) {
    }

    public function list(string $companyId): Collection
    {
        return FixedAsset::query()
            ->where('company_id', $companyId)
            ->orderBy('asset_code')
            ->limit(200)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): FixedAsset
    {
        $tenantId = $this->requireTenantId();
        $cost = round((float) ($data['acquisition_cost'] ?? 0), 4);
        $salvage = round((float) ($data['salvage_value'] ?? 0), 4);
        $months = (int) ($data['useful_life_months'] ?? 0);

        if ($cost <= 0 || $months < 1) {
            throw new DomainException('بهای تمام‌شده و عمر مفید الزامی است.', 'fin.fa.invalid_cost_life');
        }

        return FixedAsset::create([
            'fixed_asset_id'          => (string) Str::uuid(),
            'tenant_id'               => $tenantId,
            'company_id'              => $data['company_id'],
            'asset_code'              => trim((string) $data['asset_code']),
            'name'                    => (string) $data['name'],
            'asset_account_id'        => $data['asset_account_id'],
            'accum_depr_account_id'   => $data['accum_depr_account_id'],
            'depr_expense_account_id' => $data['depr_expense_account_id'],
            'cost_center_id'          => $data['cost_center_id'] ?? null,
            'acquisition_date'        => $data['acquisition_date'],
            'acquisition_cost'        => $cost,
            'salvage_value'           => $salvage,
            'useful_life_months'      => $months,
            'depreciation_method'     => $data['depreciation_method'] ?? 'STRAIGHT_LINE',
            'book_value'              => $cost,
            'accumulated_depreciation'=> 0,
            'status'                  => FixedAsset::STATUS_ACTIVE,
            'row_version'             => 1,
        ]);
    }

    /**
     * Run monthly straight-line depreciation for active assets → DRAFT journal.
     *
     * @return array{run: DepreciationRun, journal_entry_id: string|null}
     */
    public function runDepreciation(
        string $companyId,
        string $periodId,
        string $ledgerId,
        ?string $actorId = null
    ): array {
        $tenantId = $this->requireTenantId();

        if (! Ledger::where('ledger_id', $ledgerId)->exists()) {
            throw new DomainException('دفتر کل نامعتبر است.', 'fin.fa.invalid_ledger');
        }

        $assets = FixedAsset::query()
            ->where('company_id', $companyId)
            ->where('status', FixedAsset::STATUS_ACTIVE)
            ->get();

        if ($assets->isEmpty()) {
            throw new DomainException('دارایی فعالی برای استهلاک نیست.', 'fin.fa.no_assets');
        }

        return DB::transaction(function () use ($tenantId, $companyId, $periodId, $ledgerId, $assets, $actorId) {
            $runId = (string) Str::uuid();
            $total = 0.0;
            $lines = [];
            $journalLines = [];

            foreach ($assets as $asset) {
                $amount = $this->monthlyAmount($asset);
                if ($amount <= 0) {
                    continue;
                }

                $bookAfter = round((float) $asset->book_value - $amount, 4);
                if ($bookAfter < (float) $asset->salvage_value) {
                    $amount = round((float) $asset->book_value - (float) $asset->salvage_value, 4);
                    $bookAfter = (float) $asset->salvage_value;
                }
                if ($amount <= 0) {
                    $asset->status = FixedAsset::STATUS_FULLY_DEPRECIATED;
                    $asset->save();
                    continue;
                }

                $total += $amount;
                $lines[] = [
                    'depreciation_line_id' => (string) Str::uuid(),
                    'depreciation_run_id'  => $runId,
                    'tenant_id'            => $tenantId,
                    'fixed_asset_id'       => $asset->fixed_asset_id,
                    'amount'               => $amount,
                    'book_value_after'     => $bookAfter,
                    'created_at'           => now(),
                ];

                // Expense debit
                $journalLines[] = [
                    'account_id'      => $asset->depr_expense_account_id,
                    'debit_amount'    => $amount,
                    'credit_amount'   => 0,
                    'cost_center_id'  => $asset->cost_center_id,
                    'description'     => 'استهلاک '.$asset->asset_code,
                ];
                // Accum credit
                $journalLines[] = [
                    'account_id'      => $asset->accum_depr_account_id,
                    'debit_amount'    => 0,
                    'credit_amount'   => $amount,
                    'cost_center_id'  => $asset->cost_center_id,
                    'description'     => 'استهلاک انباشته '.$asset->asset_code,
                ];

                $asset->book_value = $bookAfter;
                $asset->accumulated_depreciation = round((float) $asset->accumulated_depreciation + $amount, 4);
                $asset->last_depreciated_through = now()->toDateString();
                if ($bookAfter <= (float) $asset->salvage_value + 0.0001) {
                    $asset->status = FixedAsset::STATUS_FULLY_DEPRECIATED;
                }
                $asset->row_version = ((int) $asset->row_version) + 1;
                $asset->save();
            }

            if ($journalLines === []) {
                throw new DomainException('مبلغ استهلاک صفر است.', 'fin.fa.zero_depr');
            }

            $draft = $this->journals->createDraft([
                'ledger_id'            => $ledgerId,
                'company_id'           => $companyId,
                'period_id'            => $periodId,
                'document_date'        => now()->toDateString(),
                'description'          => 'استهلاک دوره (پیش‌نویس — ثبت قطعی نشده)',
                'source_document_type' => 'DEPRECIATION_RUN',
                'source_document_id'   => $runId,
                'lines'                => $journalLines,
            ]);

            $run = DepreciationRun::create([
                'depreciation_run_id' => $runId,
                'tenant_id'           => $tenantId,
                'company_id'          => $companyId,
                'period_id'           => $periodId,
                'ledger_id'           => $ledgerId,
                'run_date'            => now()->toDateString(),
                'status'              => DepreciationRun::STATUS_POSTED_AS_JOURNAL, // means linked to draft JE
                'journal_entry_id'    => $draft->journal_entry_id,
                'total_amount'        => round($total, 4),
                'asset_count'         => count($lines),
                'created_by'          => $actorId,
                'row_version'         => 1,
            ]);

            foreach ($lines as $line) {
                DepreciationRunLine::create($line);
            }

            return [
                'run'              => $run->fresh(['lines']),
                'journal_entry_id' => $draft->journal_entry_id,
            ];
        });
    }

    protected function monthlyAmount(FixedAsset $asset): float
    {
        $depreciable = (float) $asset->acquisition_cost - (float) $asset->salvage_value;
        $months = max(1, (int) $asset->useful_life_months);

        return round($depreciable / $months, 4);
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
