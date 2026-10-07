<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Application\Services;

use App\Modules\FinancialAccounting\Infrastructure\Models\Account;

/**
 * FIN-P0-15 — P&L from trial balance rows (revenue − expense).
 */
class ProfitAndLossService
{
    public function __construct(
        protected TrialBalanceService $trialBalance = new TrialBalanceService()
    ) {
    }

    /**
     * @return array{revenue: list<array>, expense: list<array>, total_revenue: string, total_expense: string, net_income: string}
     */
    public function run(string $companyId, string $periodId): array
    {
        $tb = $this->trialBalance->run($companyId, $periodId);

        $revenue = [];
        $expense = [];
        $totalRev = 0.0;
        $totalExp = 0.0;

        foreach ($tb as $row) {
            $type = (int) $row['account_type'];
            $bal = (float) $row['balance'];
            if ($type === Account::TYPE_REVENUE) {
                // credit-nature: negative balance means credit excess → income positive as -balance
                $amount = -$bal;
                $revenue[] = array_merge($row, ['amount' => number_format($amount, 4, '.', '')]);
                $totalRev += $amount;
            } elseif ($type === Account::TYPE_EXPENSE) {
                $amount = $bal;
                $expense[] = array_merge($row, ['amount' => number_format($amount, 4, '.', '')]);
                $totalExp += $amount;
            }
        }

        $net = $totalRev - $totalExp;

        return [
            'revenue'       => $revenue,
            'expense'       => $expense,
            'total_revenue' => number_format($totalRev, 4, '.', ''),
            'total_expense' => number_format($totalExp, 4, '.', ''),
            'net_income'    => number_format($net, 4, '.', ''),
        ];
    }
}
