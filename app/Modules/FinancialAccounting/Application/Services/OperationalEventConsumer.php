<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Application\Services;

use App\Base\Context\TenantContext;
use App\Base\Exceptions\DomainException;
use App\Modules\FinancialAccounting\Infrastructure\Models\Ledger;
use App\Modules\FinancialAccounting\Infrastructure\Models\SuggestedJournal;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * FIN-P3-03 — Consume operational sales/purch events → suggested journal PENDING only.
 * Never posts to GL automatically.
 */
class OperationalEventConsumer
{
    /** Contract event types (string names used by outbox ProcessOutboxMessageJob). */
    public const SALES_INVOICE_POSTED = 'sales.invoice.posted.v1';

    public const PURCH_INVOICE_POSTED = 'purch.invoice.posted.v1';

    /** Internal determination keys (account_determination_rules.event_type). */
    public const DET_SALES = 'SALES_INVOICE_POSTED';

    public const DET_PURCH = 'PURCH_INVOICE_POSTED';

    public function __construct(
        protected SuggestedJournalService $suggestions = new SuggestedJournalService()
    ) {
    }

    /**
     * Entry from Laravel event() string dispatch (outbox worker).
     *
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): ?SuggestedJournal
    {
        $eventType = (string) ($payload['event_type'] ?? $payload['_event_type'] ?? '');

        return $this->consume($eventType, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function consume(string $eventType, array $payload): ?SuggestedJournal
    {
        $mapped = $this->mapEventType($eventType);
        if ($mapped === null) {
            return null;
        }

        $tenantId = (string) ($payload['tenant_id'] ?? '');
        if ($tenantId !== '') {
            TenantContext::getInstance()->setTenantId($tenantId);
        }

        $companyId = (string) ($payload['company_id'] ?? '');
        $documentId = (string) ($payload['document_id'] ?? $payload['invoice_id'] ?? $payload['source_document_id'] ?? '');
        $periodId = (string) ($payload['period_id'] ?? '');
        $ledgerId = (string) ($payload['ledger_id'] ?? '');

        if ($companyId === '' || $documentId === '') {
            Log::warning('finance.operational_consumer.missing_ids', [
                'event_type' => $eventType,
                'payload'    => $payload,
            ]);

            return null;
        }

        if ($ledgerId === '') {
            $ledger = Ledger::query()
                ->where('company_id', $companyId)
                ->where('is_leading', true)
                ->where('status', 1)
                ->first();
            $ledgerId = $ledger?->ledger_id ?? '';
        }

        if ($ledgerId === '') {
            throw new DomainException(
                'دفتر کل پیش‌فرض برای شرکت یافت نشد.',
                'fin.consumer.ledger_missing'
            );
        }

        if ($periodId === '') {
            // Demo / soft fallback until fiscal calendar is always on payload
            $periodId = 'a1000000-0000-4000-8000-000000000001';
        }

        $amounts = $this->extractAmounts($mapped, $payload);
        if ($amounts === []) {
            Log::warning('finance.operational_consumer.empty_amounts', [
                'event_type' => $eventType,
            ]);

            return null;
        }

        // Idempotency: one suggestion per source document + event
        $existing = SuggestedJournal::query()
            ->where('source_event_type', $mapped)
            ->where('source_document_id', $documentId)
            ->whereIn('status', [
                SuggestedJournal::STATUS_PENDING,
                SuggestedJournal::STATUS_ACCEPTED,
            ])
            ->first();

        if ($existing) {
            return $existing->load('lines');
        }

        return $this->suggestions->suggestFromOperational([
            'company_id'         => $companyId,
            'ledger_id'          => $ledgerId,
            'period_id'          => $periodId,
            'source_event_type'  => $mapped,
            'source_document_id' => $documentId,
            'description'        => (string) ($payload['description'] ?? ('پیشنهاد از '.$eventType)),
            'amounts'            => $amounts,
        ]);
    }

    protected function mapEventType(string $eventType): ?string
    {
        return match ($eventType) {
            self::SALES_INVOICE_POSTED, self::DET_SALES => self::DET_SALES,
            self::PURCH_INVOICE_POSTED, self::DET_PURCH => self::DET_PURCH,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, float>
     */
    protected function extractAmounts(string $detEvent, array $payload): array
    {
        if (isset($payload['amounts']) && is_array($payload['amounts'])) {
            $out = [];
            foreach ($payload['amounts'] as $role => $amt) {
                $out[(string) $role] = (float) $amt;
            }

            return $out;
        }

        $net = (float) ($payload['net_amount'] ?? $payload['amount'] ?? 0);
        $tax = (float) ($payload['tax_amount'] ?? 0);
        $gross = (float) ($payload['gross_amount'] ?? ($net + $tax));

        if ($detEvent === self::DET_SALES) {
            $amounts = [];
            if ($gross > 0) {
                $amounts['RECEIVABLE'] = $gross;
            }
            if ($net > 0) {
                $amounts['REVENUE'] = $net;
            }
            if ($tax > 0) {
                $amounts['TAX_OUTPUT'] = $tax;
            }

            return $amounts;
        }

        if ($detEvent === self::DET_PURCH) {
            $amounts = [];
            if ($gross > 0) {
                $amounts['PAYABLE'] = $gross;
            }
            if ($net > 0) {
                $amounts['EXPENSE'] = $net;
            }
            if ($tax > 0) {
                $amounts['TAX_INPUT'] = $tax;
            }

            return $amounts;
        }

        return [];
    }
}
