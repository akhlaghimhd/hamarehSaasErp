<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Application\Services;

use App\Base\Context\TenantContext;
use App\Base\Exceptions\DomainException;
use App\Modules\FinancialAccounting\Infrastructure\Models\ComplianceAlert;
use App\Modules\FinancialAccounting\Infrastructure\Models\JournalEntry;
use App\Modules\FinancialAccounting\Infrastructure\Models\PeriodCloseChecklist;
use App\Modules\FinancialAccounting\Infrastructure\Models\PeriodControl;
use App\Modules\FinancialAccounting\Infrastructure\Models\SuggestedJournal;
use Illuminate\Support\Str;

/**
 * FIN-P4-05/06 K4 — Guided period close checklist; blocks hard-close when critical items open.
 */
class PeriodCloseChecklistService
{
    public function __construct(
        protected FiscalPeriodControlService $periods = new FiscalPeriodControlService()
    ) {
    }

    /**
     * @return array{checklist: PeriodCloseChecklist, items: list<array<string, mixed>>}
     */
    public function evaluate(string $companyId, string $periodId, ?string $actorId = null): array
    {
        $tenantId = $this->requireTenantId();
        $items = [];

        $draftCount = JournalEntry::query()
            ->where('company_id', $companyId)
            ->where('period_id', $periodId)
            ->where('status', JournalEntry::STATUS_DRAFT)
            ->count();

        if ($draftCount > 0) {
            $items[] = [
                'code'     => 'OPEN_DRAFT_JOURNALS',
                'severity' => 'BLOCK',
                'title'    => 'پیش‌نویس سند باز',
                'message'  => "{$draftCount} سند پیش‌نویس در این دوره باز است.",
                'blocking' => true,
            ];
        }

        $pendingSug = SuggestedJournal::query()
            ->where('company_id', $companyId)
            ->where('period_id', $periodId)
            ->where('status', SuggestedJournal::STATUS_PENDING)
            ->count();

        if ($pendingSug > 0) {
            $items[] = [
                'code'     => 'PENDING_SUGGESTIONS',
                'severity' => 'BLOCK',
                'title'    => 'پیشنهاد هوشمند باز',
                'message'  => "{$pendingSug} پیشنهاد در صندوق در انتظار تصمیم است.",
                'blocking' => true,
            ];
        }

        if (class_exists(ComplianceAlert::class)) {
            $alerts = ComplianceAlert::query()
                ->where('company_id', $companyId)
                ->where('is_resolved', false)
                ->where('severity', 'BLOCK')
                ->count();

            if ($alerts > 0) {
                $items[] = [
                    'code'     => 'COMPLIANCE_BLOCK',
                    'severity' => 'BLOCK',
                    'title'    => 'هشدار انطباق مسدودکننده',
                    'message'  => "{$alerts} هشدار BLOCK باز است.",
                    'blocking' => true,
                ];
            }
        }

        $control = $this->periods->getOrCreate($companyId, $periodId);
        if ($control->control_status === PeriodControl::STATUS_HARD_CLOSED) {
            $items[] = [
                'code'     => 'ALREADY_HARD_CLOSED',
                'severity' => 'INFO',
                'title'    => 'دوره قبلاً قطعی بسته شده',
                'message'  => 'نیازی به بستن مجدد نیست.',
                'blocking' => false,
            ];
        }

        if ($items === []) {
            $items[] = [
                'code'     => 'READY',
                'severity' => 'INFO',
                'title'    => 'آماده بستن',
                'message'  => 'مورد مسدودکنندهٔ باز یافت نشد.',
                'blocking' => false,
            ];
        }

        $hasBlocking = collect($items)->contains(fn ($i) => ! empty($i['blocking']));

        $row = PeriodCloseChecklist::create([
            'checklist_id' => (string) Str::uuid(),
            'tenant_id'    => $tenantId,
            'company_id'   => $companyId,
            'period_id'    => $periodId,
            'items_json'   => $items,
            'has_blocking' => $hasBlocking,
            'evaluated_at' => now(),
            'evaluated_by' => $actorId,
            'created_at'   => now(),
        ]);

        return ['checklist' => $row, 'items' => $items];
    }

    public function softCloseGuided(string $companyId, string $periodId, ?string $actorId = null): PeriodControl
    {
        $eval = $this->evaluate($companyId, $periodId, $actorId);
        if ($eval['checklist']->has_blocking) {
            throw new DomainException(
                'بستن دوره به‌خاطر موارد مسدودکننده ممکن نیست. ابتدا چک‌لیست را رفع کنید.',
                'fin.period.close_blocked'
            );
        }

        return $this->periods->softClose($companyId, $periodId, $actorId);
    }

    public function hardCloseGuided(string $companyId, string $periodId, ?string $actorId = null): PeriodControl
    {
        $eval = $this->evaluate($companyId, $periodId, $actorId);
        if ($eval['checklist']->has_blocking) {
            throw new DomainException(
                'بستن قطعی دوره با موارد بحرانی باز مجاز نیست.',
                'fin.period.hard_close_blocked'
            );
        }

        return $this->periods->hardClose($companyId, $periodId, $actorId);
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
