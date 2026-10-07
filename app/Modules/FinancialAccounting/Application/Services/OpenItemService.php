<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Application\Services;

use App\Base\Context\TenantContext;
use App\Base\Exceptions\DomainException;
use App\Modules\FinancialAccounting\Infrastructure\Models\OpenItem;
use App\Modules\FinancialAccounting\Infrastructure\Models\OpenItemAllocation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** FIN-P1-08/09 — AR/AP open items, allocate, aging. */
class OpenItemService
{
    public function registerManualInvoice(array $data): OpenItem
    {
        $tenantId = $this->requireTenantId();
        $side = (string) ($data['side'] ?? '');
        if (! in_array($side, [OpenItem::SIDE_AR, OpenItem::SIDE_AP], true)) {
            throw new DomainException('طرف حساب (AR/AP) نامعتبر است.', 'fin.oi.side');
        }

        $amount = round((float) ($data['original_amount'] ?? 0), 4);
        if ($amount <= 0) {
            throw new DomainException('مبلغ فاکتور باید مثبت باشد.', 'fin.oi.amount');
        }

        return OpenItem::create([
            'open_item_id'        => (string) Str::uuid(),
            'tenant_id'           => $tenantId,
            'company_id'          => $data['company_id'],
            'side'                => $side,
            'document_type'       => $data['document_type'] ?? 'MANUAL_INVOICE',
            'document_number'     => $data['document_number'] ?? null,
            'document_date'       => $data['document_date'],
            'due_date'            => $data['due_date'] ?? null,
            'counterparty_name'   => (string) $data['counterparty_name'],
            'counterparty_ref_id' => $data['counterparty_ref_id'] ?? null,
            'original_amount'     => $amount,
            'open_amount'         => $amount,
            'currency_id'         => $data['currency_id'] ?? null,
            'gl_account_id'       => $data['gl_account_id'] ?? null,
            'status'              => OpenItem::STATUS_OPEN,
            'description'         => $data['description'] ?? null,
            'row_version'         => 1,
        ]);
    }

    /**
     * @param  array{treasury_document_id?: string|null, journal_entry_id?: string|null, allocation_date?: string, description?: string|null}  $meta
     */
    public function allocate(string $openItemId, float $amount, array $meta = []): OpenItem
    {
        $item = OpenItem::where('open_item_id', $openItemId)->firstOrFail();

        if (in_array($item->status, [OpenItem::STATUS_CLOSED, OpenItem::STATUS_VOID], true)) {
            throw new DomainException('آیتم بسته یا باطل است.', 'fin.oi.closed');
        }

        $amount = round($amount, 4);
        if ($amount <= 0 || $amount > (float) $item->open_amount + 0.00005) {
            throw new DomainException('مبلغ تخصیص نامعتبر است.', 'fin.oi.alloc_amount');
        }

        return DB::transaction(function () use ($item, $amount, $meta) {
            OpenItemAllocation::create([
                'allocation_id'        => (string) Str::uuid(),
                'tenant_id'            => $item->tenant_id,
                'open_item_id'         => $item->open_item_id,
                'treasury_document_id' => $meta['treasury_document_id'] ?? null,
                'journal_entry_id'     => $meta['journal_entry_id'] ?? null,
                'allocated_amount'     => $amount,
                'allocation_date'      => $meta['allocation_date'] ?? now()->toDateString(),
                'description'          => $meta['description'] ?? null,
                'created_at'           => now(),
            ]);

            $open = round((float) $item->open_amount - $amount, 4);
            $item->open_amount = max(0, $open);
            $item->status = $item->open_amount <= 0.0001
                ? OpenItem::STATUS_CLOSED
                : OpenItem::STATUS_PARTIAL;
            $item->row_version = ((int) ($item->row_version ?? 1)) + 1;
            $item->save();

            return $item->fresh();
        });
    }

    /**
     * Aging buckets: current, 1-30, 31-60, 61-90, 90+.
     *
     * @return list<array{bucket: string, count: int, amount: string}>
     */
    public function aging(string $companyId, string $side, ?string $asOf = null): array
    {
        $asOfDate = $asOf ? new \DateTimeImmutable($asOf) : new \DateTimeImmutable('today');

        $items = OpenItem::query()
            ->where('company_id', $companyId)
            ->where('side', $side)
            ->whereIn('status', [OpenItem::STATUS_OPEN, OpenItem::STATUS_PARTIAL])
            ->get();

        $buckets = [
            'current' => 0.0,
            '1_30'    => 0.0,
            '31_60'   => 0.0,
            '61_90'   => 0.0,
            '90_plus' => 0.0,
        ];
        $counts = array_fill_keys(array_keys($buckets), 0);

        foreach ($items as $item) {
            $due = $item->due_date
                ? \DateTimeImmutable::createFromMutable($item->due_date->toDateTime())
                : \DateTimeImmutable::createFromMutable($item->document_date->toDateTime());
            $days = (int) $asOfDate->diff($due)->format('%r%a');
            // positive days = overdue
            $overdue = $days < 0 ? abs($days) : 0;

            $key = match (true) {
                $overdue === 0 => 'current',
                $overdue <= 30 => '1_30',
                $overdue <= 60 => '31_60',
                $overdue <= 90 => '61_90',
                default => '90_plus',
            };

            $buckets[$key] += (float) $item->open_amount;
            $counts[$key]++;
        }

        $out = [];
        foreach ($buckets as $k => $amt) {
            $out[] = [
                'bucket' => $k,
                'count'  => $counts[$k],
                'amount' => number_format($amt, 4, '.', ''),
            ];
        }

        return $out;
    }

    public function listOpen(string $companyId, ?string $side = null): Collection
    {
        $q = OpenItem::query()
            ->where('company_id', $companyId)
            ->whereIn('status', [OpenItem::STATUS_OPEN, OpenItem::STATUS_PARTIAL]);

        if ($side) {
            $q->where('side', $side);
        }

        return $q->orderBy('due_date')->get();
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
