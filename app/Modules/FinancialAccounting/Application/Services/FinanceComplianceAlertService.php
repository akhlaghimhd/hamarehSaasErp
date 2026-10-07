<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Application\Services;

use App\Base\Context\TenantContext;
use App\Base\Exceptions\DomainException;
use App\Modules\FinancialAccounting\Infrastructure\Models\ComplianceAlert;
use App\Modules\FinancialAccounting\Infrastructure\Models\MoodianSubmission;
use App\Modules\FinancialAccounting\Infrastructure\Models\TaxTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** FIN-P2-08 K3 — Missing Moodian submit, gaps, rate issues. */
class FinanceComplianceAlertService
{
    public function raise(
        string $alertCode,
        string $title,
        string $message,
        string $severity = ComplianceAlert::SEV_WARN,
        ?string $companyId = null,
        ?string $relatedType = null,
        ?string $relatedId = null
    ): ComplianceAlert {
        $tenantId = $this->requireTenantId();

        return ComplianceAlert::create([
            'compliance_alert_id' => (string) Str::uuid(),
            'tenant_id'           => $tenantId,
            'company_id'          => $companyId,
            'alert_code'          => $alertCode,
            'severity'            => $severity,
            'title'               => $title,
            'message'             => $message,
            'related_type'        => $relatedType,
            'related_id'          => $relatedId,
            'is_resolved'         => false,
            'created_at'          => now(),
        ]);
    }

    public function listOpen(?string $companyId = null): Collection
    {
        $q = ComplianceAlert::query()->where('is_resolved', false)->orderByDesc('created_at');
        if ($companyId) {
            $q->where('company_id', $companyId);
        }

        return $q->limit(200)->get();
    }

    public function resolve(string $alertId, ?string $actorId = null): ComplianceAlert
    {
        $alert = ComplianceAlert::where('compliance_alert_id', $alertId)->firstOrFail();
        $alert->is_resolved = true;
        $alert->resolved_at = now();
        $alert->resolved_by = $actorId;
        $alert->save();

        return $alert->fresh();
    }

    /**
     * Scan tax txns without successful Moodian acceptance.
     */
    public function scanMissingMoodian(string $companyId): int
    {
        $txns = TaxTransaction::query()
            ->where('company_id', $companyId)
            ->get();

        $raised = 0;
        foreach ($txns as $txn) {
            $ok = MoodianSubmission::query()
                ->where('source_document_type', $txn->source_document_type)
                ->where('source_document_id', $txn->source_document_id)
                ->whereIn('status', [
                    MoodianSubmission::STATUS_SUBMITTED,
                    MoodianSubmission::STATUS_ACCEPTED,
                ])
                ->exists();

            if ($ok) {
                continue;
            }

            $exists = ComplianceAlert::query()
                ->where('alert_code', 'MOODIAN_MISSING')
                ->where('related_id', $txn->tax_transaction_id)
                ->where('is_resolved', false)
                ->exists();

            if ($exists) {
                continue;
            }

            $this->raise(
                'MOODIAN_MISSING',
                'ارسال مودیان انجام نشده',
                "تراکنش مالیاتی {$txn->tax_transaction_id} هنوز در مودیان ثبت موفق ندارد.",
                ComplianceAlert::SEV_WARN,
                $companyId,
                'TAX_TRANSACTION',
                $txn->tax_transaction_id
            );
            $raised++;
        }

        return $raised;
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
