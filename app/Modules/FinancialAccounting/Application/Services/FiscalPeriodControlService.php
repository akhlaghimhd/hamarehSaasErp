<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Application\Services;

use App\Base\Context\TenantContext;
use App\Base\Exceptions\DomainException;
use App\Modules\FinancialAccounting\Infrastructure\Models\PeriodControl;
use Illuminate\Support\Str;

/**
 * FIN-P0-11 — OPEN / SOFT_CLOSED / HARD_CLOSED controls for posting.
 */
class FiscalPeriodControlService
{
    public function getOrCreate(string $companyId, string $periodId): PeriodControl
    {
        $tenantId = $this->requireTenantId();

        $existing = PeriodControl::query()
            ->where('company_id', $companyId)
            ->where('period_id', $periodId)
            ->first();

        if ($existing) {
            return $existing;
        }

        return PeriodControl::create([
            'period_control_id' => (string) Str::uuid(),
            'tenant_id'         => $tenantId,
            'company_id'        => $companyId,
            'period_id'         => $periodId,
            'control_status'    => PeriodControl::STATUS_OPEN,
            'row_version'       => 1,
        ]);
    }

    public function softClose(string $companyId, string $periodId, ?string $actorId = null): PeriodControl
    {
        $control = $this->getOrCreate($companyId, $periodId);

        if ($control->isHardClosed()) {
            throw new DomainException(
                'دوره به‌صورت قطعی بسته است و قابل نیمه‌بستن نیست.',
                'fin.period.hard_closed'
            );
        }

        $control->control_status = PeriodControl::STATUS_SOFT_CLOSED;
        $control->soft_closed_at = now();
        $control->soft_closed_by = $actorId;
        $control->row_version = ((int) ($control->row_version ?? 1)) + 1;
        $control->save();

        return $control->fresh();
    }

    public function hardClose(string $companyId, string $periodId, ?string $actorId = null): PeriodControl
    {
        $control = $this->getOrCreate($companyId, $periodId);

        $control->control_status = PeriodControl::STATUS_HARD_CLOSED;
        $control->hard_closed_at = now();
        $control->hard_closed_by = $actorId;
        if ($control->soft_closed_at === null) {
            $control->soft_closed_at = now();
            $control->soft_closed_by = $actorId;
        }
        $control->row_version = ((int) ($control->row_version ?? 1)) + 1;
        $control->save();

        return $control->fresh();
    }

    public function reopen(string $companyId, string $periodId): PeriodControl
    {
        $control = $this->getOrCreate($companyId, $periodId);

        if ($control->isHardClosed()) {
            throw new DomainException(
                'بازگشایی دوره قطعی‌بسته فقط با مسیر کنترل‌شده مجاز است.',
                'fin.period.cannot_reopen_hard'
            );
        }

        $control->control_status = PeriodControl::STATUS_OPEN;
        $control->soft_closed_at = null;
        $control->soft_closed_by = null;
        $control->row_version = ((int) ($control->row_version ?? 1)) + 1;
        $control->save();

        return $control->fresh();
    }

    /**
     * Guard used by JournalEntryService::post / reverse.
     */
    public function assertPostingAllowed(string $companyId, string $periodId): void
    {
        $control = $this->getOrCreate($companyId, $periodId);

        if ($control->control_status === PeriodControl::STATUS_HARD_CLOSED) {
            throw new DomainException(
                'دوره مالی به‌صورت قطعی بسته است؛ ثبت یا برگشت سند مجاز نیست.',
                'fin.period.hard_closed_blocks_post'
            );
        }

        if ($control->control_status === PeriodControl::STATUS_SOFT_CLOSED) {
            throw new DomainException(
                'دوره مالی نیمه‌بسته است؛ ثبت سند جدید مجاز نیست.',
                'fin.period.soft_closed_blocks_post'
            );
        }
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
