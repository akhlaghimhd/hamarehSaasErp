<?php

declare(strict_types=1);

namespace App\Modules\FinancialAccounting\Application\Services;

/**
 * FIN-P6-02 K5 — non-blocking managerial insight texts on P&L.
 */
class PlInsightService
{
    public function __construct(
        protected ProfitAndLossService $pl = new ProfitAndLossService(),
        protected SmartActionAuditService $audit = new SmartActionAuditService()
    ) {
    }

    /**
     * @return array{insights: list<array{code: string, severity: string, text: string}>, pl: array}
     */
    public function insights(string $companyId, string $periodId, ?string $actorId = null): array
    {
        $pl = $this->pl->run($companyId, $periodId);
        $net = (float) $pl['net_income'];
        $rev = (float) $pl['total_revenue'];
        $exp = (float) $pl['total_expense'];

        $insights = [];

        if ($rev <= 0 && $exp <= 0) {
            $insights[] = [
                'code'     => 'PL_EMPTY',
                'severity' => 'info',
                'text'     => 'در این دوره درآمد و هزینه‌ای ثبت نشده است.',
            ];
        } else {
            if ($net < 0) {
                $insights[] = [
                    'code'     => 'PL_LOSS',
                    'severity' => 'warn',
                    'text'     => 'نتیجه دوره زیان است؛ هزینه‌ها از درآمد بیشترند.',
                ];
            } elseif ($net > 0) {
                $margin = $rev > 0 ? round(($net / $rev) * 100, 1) : 0;
                $insights[] = [
                    'code'     => 'PL_PROFIT',
                    'severity' => 'info',
                    'text'     => "سود خالص مثبت است (حاشیه تقریبی {$margin}٪ از درآمد).",
                ];
            }

            if ($rev > 0 && $exp / max($rev, 0.0001) > 0.9) {
                $insights[] = [
                    'code'     => 'PL_HIGH_EXPENSE_RATIO',
                    'severity' => 'warn',
                    'text'     => 'نسبت هزینه به درآمد بالاست (بیش از ۹۰٪).',
                ];
            }

            // top expense concentration
            $expenses = $pl['expense'] ?? [];
            if (count($expenses) > 0) {
                usort($expenses, fn ($a, $b) => (float) $b['amount'] <=> (float) $a['amount']);
                $top = $expenses[0];
                $topAmt = (float) $top['amount'];
                if ($exp > 0 && $topAmt / $exp >= 0.4) {
                    $insights[] = [
                        'code'     => 'PL_EXPENSE_CONCENTRATION',
                        'severity' => 'info',
                        'text'     => 'بخش عمده هزینه در حساب «'.($top['name'] ?? $top['account_code']).'» متمرکز است.',
                    ];
                }
            }
        }

        $this->audit->log('K5', 'PL_INSIGHT', 'GENERATED', [
            'company_id' => $companyId,
            'period_id'  => $periodId,
            'count'      => count($insights),
        ], $actorId);

        return [
            'insights' => $insights,
            'pl'       => $pl,
        ];
    }
}
