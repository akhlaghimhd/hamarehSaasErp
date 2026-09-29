<?php

namespace App\Modules\Organization\Services;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * ORG-W2-05 — Effective ownership graph + minority interest.
 *
 * Direct links live in erp_company_ownerships.
 * Effective (look-through) ownership multiplies along paths.
 * Minority interest at a node = max(0, 100 − sum of controlling direct %).
 *
 * Full consol elim journal still belongs to Accounting (ORG-W2-01/02).
 */
class OwnershipEffectiveService
{
    /**
     * Direct ownership rows for a company as of a date (null = today).
     *
     * @return list<array{ownership_id:string,owner_company_id:string,ownership_percent:float,relation_type:string}>
     */
    public function directOwners(string $tenantId, string $companyId, ?string $asOf = null): array
    {
        $asOfDate = $asOf ? \Carbon\Carbon::parse($asOf)->toDateString() : now()->toDateString();

        $rows = DB::table('erp_company_ownerships')
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($asOfDate) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', $asOfDate);
            })
            ->where(function ($q) use ($asOfDate) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', $asOfDate);
            })
            ->orderByDesc('ownership_percent')
            ->get([
                'ownership_id',
                'owner_company_id',
                'ownership_percent',
                'relation_type',
            ]);

        return $rows->map(fn ($r) => [
            'ownership_id'       => (string) $r->ownership_id,
            'owner_company_id'   => (string) $r->owner_company_id,
            'ownership_percent'  => (float) $r->ownership_percent,
            'relation_type'      => (string) $r->relation_type,
        ])->all();
    }

    /**
     * Sum of direct ownership percent (capped conceptually at 100 for minority calc).
     */
    public function directOwnershipTotal(string $tenantId, string $companyId, ?string $asOf = null): float
    {
        $sum = 0.0;
        foreach ($this->directOwners($tenantId, $companyId, $asOf) as $row) {
            $sum += $row['ownership_percent'];
        }

        return round($sum, 4);
    }

    /**
     * Minority interest % = max(0, 100 − directOwnershipTotal).
     */
    public function minorityInterestPercent(string $tenantId, string $companyId, ?string $asOf = null): float
    {
        $owned = $this->directOwnershipTotal($tenantId, $companyId, $asOf);

        return round(max(0.0, 100.0 - $owned), 4);
    }

    /**
     * Effective ownership % of $ownerCompanyId over $companyId via look-through paths.
     * Multiple paths are summed (capped at 100).
     */
    public function effectiveOwnershipPercent(
        string $tenantId,
        string $ownerCompanyId,
        string $companyId,
        ?string $asOf = null
    ): float {
        if ($ownerCompanyId === $companyId) {
            return 100.0;
        }

        $graph = $this->buildChildToOwnersGraph($tenantId, $asOf);
        $pct = $this->walkEffective($graph, $ownerCompanyId, $companyId, [], 100.0);

        return round(min(100.0, $pct), 4);
    }

    /**
     * Snapshot used by consol prep: for each company, direct total + minority + listed owners.
     *
     * @param  list<string>  $companyIds
     * @return array<string, array{direct_total:float,minority_interest:float,owners:list<array>}>
     */
    public function ownershipSnapshot(string $tenantId, array $companyIds, ?string $asOf = null): array
    {
        $out = [];
        foreach (array_unique($companyIds) as $cid) {
            $owners = $this->directOwners($tenantId, $cid, $asOf);
            $direct = 0.0;
            foreach ($owners as $o) {
                $direct += $o['ownership_percent'];
            }
            $direct = round($direct, 4);
            $out[$cid] = [
                'direct_total'       => $direct,
                'minority_interest'  => round(max(0.0, 100.0 - $direct), 4),
                'owners'             => $owners,
            ];
        }

        return $out;
    }

    public function assertOwnershipPercentSane(string $tenantId, string $companyId, ?string $asOf = null): void
    {
        $total = $this->directOwnershipTotal($tenantId, $companyId, $asOf);
        if ($total > 100.0001) {
            throw new HttpException(
                422,
                "مجموع درصد مالکیت مستقیم شرکت از ۱۰۰٪ بیشتر است ({$total})."
            );
        }
    }

    /**
     * @return array<string, list<array{owner_company_id:string,ownership_percent:float}>>
     */
    private function buildChildToOwnersGraph(string $tenantId, ?string $asOf): array
    {
        $asOfDate = $asOf ? \Carbon\Carbon::parse($asOf)->toDateString() : now()->toDateString();

        $rows = DB::table('erp_company_ownerships')
            ->where('tenant_id', $tenantId)
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($asOfDate) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', $asOfDate);
            })
            ->where(function ($q) use ($asOfDate) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', $asOfDate);
            })
            ->get(['company_id', 'owner_company_id', 'ownership_percent']);

        $graph = [];
        foreach ($rows as $r) {
            $child = (string) $r->company_id;
            $graph[$child][] = [
                'owner_company_id'  => (string) $r->owner_company_id,
                'ownership_percent' => (float) $r->ownership_percent,
            ];
        }

        return $graph;
    }

    /**
     * Sum of path products from target company up to owner.
     * pathProduct is expressed in percent units (100 = 100%).
     *
     * @param  array<string,bool>  $visited
     */
    private function walkEffective(
        array $graph,
        string $targetOwner,
        string $currentCompany,
        array $visited,
        float $pathProduct
    ): float {
        if (isset($visited[$currentCompany])) {
            return 0.0;
        }
        $visited[$currentCompany] = true;

        $sum = 0.0;
        foreach ($graph[$currentCompany] ?? [] as $link) {
            $ownerId = $link['owner_company_id'];
            $segment = $pathProduct * ($link['ownership_percent'] / 100.0);

            if ($ownerId === $targetOwner) {
                $sum += $segment;
                continue;
            }

            $sum += $this->walkEffective($graph, $targetOwner, $ownerId, $visited, $segment);
        }

        return $sum;
    }
}
