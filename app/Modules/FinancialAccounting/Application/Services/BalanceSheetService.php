<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Application\Services;

use App\Modules\FinancialAccounting\Infrastructure\Models\Account;

/**
 * FIN-P0-15 — Balance sheet snapshot from trial balance + net income into equity.
 */
class BalanceSheetService
{
    public function __construct(
        protected TrialBalanceService $trialBalance = new TrialBalanceService(),
        protected ProfitAndLossService $pl = new ProfitAndLossService()
    ) {
    }

    /**
     * @return array{assets: list<array>, liabilities: list<array>, equity: list<array>, total_assets: string, total_liabilities_equity: string}
     */
    public function run(string $companyId, string $periodId): array
    {
        $tb = $this->trialBalance->run($companyId, $periodId);
        $pl = $this->pl->run($companyId, $periodId);

        $assets = [];
        $liabilities = [];
        $equity = [];
        $totalAssets = 0.0;
        $totalLiabEq = 0.0;

        foreach ($tb as $row) {
            $type = (int) $row['account_type'];
            $bal = (float) $row['balance'];
            if ($type === Account::TYPE_ASSET) {
                $assets[] = array_merge($row, ['amount' => number_format($bal, 4, '.', '')]);
                $totalAssets += $bal;
            } elseif ($type === Account::TYPE_LIABILITY) {
                $amount = -$bal;
                $liabilities[] = array_merge($row, ['amount' => number_format($amount, 4, '.', '')]);
                $totalLiabEq += $amount;
            } elseif ($type === Account::TYPE_EQUITY) {
                $amount = -$bal;
                $equity[] = array_merge($row, ['amount' => number_format($amount, 4, '.', '')]);
                $totalLiabEq += $amount;
            }
        }

        $net = (float) $pl['net_income'];
        $equity[] = [
            'account_id'   => null,
            'account_code' => 'NET',
            'name'         => 'سود (زیان) دوره',
            'account_type' => Account::TYPE_EQUITY,
            'amount'       => number_format($net, 4, '.', ''),
        ];
        $totalLiabEq += $net;

        return [
            'assets'                  => $assets,
            'liabilities'             => $liabilities,
            'equity'                  => $equity,
            'total_assets'            => number_format($totalAssets, 4, '.', ''),
            'total_liabilities_equity' => number_format($totalLiabEq, 4, '.', ''),
        ];
    }
}
