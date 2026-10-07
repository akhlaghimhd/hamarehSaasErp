<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Application\Services;

use App\Base\Context\TenantContext;
use App\Base\Exceptions\DomainException;
use App\Modules\FinancialAccounting\Infrastructure\Models\TaxRateConfig;
use App\Modules\FinancialAccounting\Infrastructure\Models\TaxTransaction;
use Illuminate\Support\Str;

/** FIN-P2-03 — Split net/tax using configurable rates. */
class VatCalculationService
{
    public function upsertRate(array $data): TaxRateConfig
    {
        $tenantId = $this->requireTenantId();

        return TaxRateConfig::create([
            'tax_rate_config_id' => (string) Str::uuid(),
            'tenant_id'          => $tenantId,
            'tax_code'           => $data['tax_code'],
            'name'               => $data['name'],
            'rate_percent'       => $data['rate_percent'],
            'valid_from'         => $data['valid_from'],
            'valid_to'           => $data['valid_to'] ?? null,
            'is_default'         => (bool) ($data['is_default'] ?? false),
            'status'             => 1,
            'row_version'        => 1,
        ]);
    }

    public function resolveRate(string $taxCode, string $onDate): TaxRateConfig
    {
        $rate = TaxRateConfig::query()
            ->where('tax_code', $taxCode)
            ->where('valid_from', '<=', $onDate)
            ->where(function ($q) use ($onDate) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', $onDate);
            })
            ->orderByDesc('valid_from')
            ->first();

        if (! $rate) {
            throw new DomainException(
                "نرخ مالیاتی برای کد {$taxCode} در تاریخ {$onDate} یافت نشد.",
                'fin.tax.rate_missing'
            );
        }

        return $rate;
    }

    /**
     * @return array{net: string, tax: string, gross: string, rate: string, tax_code: string, config_id: string}
     */
    public function splitGross(float $gross, string $taxCode, string $onDate): array
    {
        $cfg = $this->resolveRate($taxCode, $onDate);
        $rate = (float) $cfg->rate_percent;
        if ($rate <= 0) {
            return [
                'net'       => number_format($gross, 4, '.', ''),
                'tax'       => '0.0000',
                'gross'     => number_format($gross, 4, '.', ''),
                'rate'      => '0.0000',
                'tax_code'  => $taxCode,
                'config_id' => $cfg->tax_rate_config_id,
            ];
        }

        $net = round($gross / (1 + $rate / 100), 4);
        $tax = round($gross - $net, 4);

        return [
            'net'       => number_format($net, 4, '.', ''),
            'tax'       => number_format($tax, 4, '.', ''),
            'gross'     => number_format($gross, 4, '.', ''),
            'rate'      => number_format($rate, 4, '.', ''),
            'tax_code'  => $taxCode,
            'config_id' => $cfg->tax_rate_config_id,
        ];
    }

    /**
     * @return array{net: string, tax: string, gross: string, rate: string, tax_code: string, config_id: string}
     */
    public function splitNet(float $net, string $taxCode, string $onDate): array
    {
        $cfg = $this->resolveRate($taxCode, $onDate);
        $rate = (float) $cfg->rate_percent;
        $tax = round($net * $rate / 100, 4);
        $gross = round($net + $tax, 4);

        return [
            'net'       => number_format($net, 4, '.', ''),
            'tax'       => number_format($tax, 4, '.', ''),
            'gross'     => number_format($gross, 4, '.', ''),
            'rate'      => number_format($rate, 4, '.', ''),
            'tax_code'  => $taxCode,
            'config_id' => $cfg->tax_rate_config_id,
        ];
    }

    public function recordTransaction(array $data): TaxTransaction
    {
        $tenantId = $this->requireTenantId();
        $onDate = (string) $data['transaction_date'];
        $split = isset($data['gross_amount'])
            ? $this->splitGross((float) $data['gross_amount'], (string) $data['tax_code'], $onDate)
            : $this->splitNet((float) $data['net_amount'], (string) $data['tax_code'], $onDate);

        return TaxTransaction::create([
            'tax_transaction_id'   => (string) Str::uuid(),
            'tenant_id'            => $tenantId,
            'company_id'           => $data['company_id'],
            'source_document_type' => $data['source_document_type'],
            'source_document_id'   => $data['source_document_id'],
            'tax_rate_config_id'   => $split['config_id'],
            'tax_code'             => $split['tax_code'],
            'taxable_amount'       => $split['net'],
            'tax_rate'             => $split['rate'],
            'tax_amount'           => $split['tax'],
            'transaction_date'     => $onDate,
            'direction'            => $data['direction'] ?? TaxTransaction::DIR_OUTPUT,
            'journal_entry_id'     => $data['journal_entry_id'] ?? null,
            'created_at'           => now(),
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
