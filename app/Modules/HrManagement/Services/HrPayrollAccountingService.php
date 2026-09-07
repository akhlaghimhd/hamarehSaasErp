<?php

namespace App\Modules\HrManagement\Services;

use App\Modules\Accounting\Services\VoucherPostingService;
use App\Modules\HrManagement\Models\PayrollRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * L6-HR-03 — Bridge HR payroll → Accounting (in-process Service Contract).
 *
 * Convention GL codes (tenant chart):
 *   6100 Salary expense | 2200 Tax payable | 2210 Insurance payable | 1100 Cash/Bank
 * Balanced entry on disburse:
 *   Dr 6100  (base + allowances)
 *   Cr 2200  tax_withheld
 *   Cr 2210  insurance_premium
 *   Cr 1100  net_payable  (+ residual if deductions_total > 0 absorbed into expense net)
 *
 * Simplified model: deductions_total reduces expense net effect by crediting cash less
 * and treating remaining as part of net to cash: gross expense = base+allowances;
 * credits = tax + insurance + (gross - tax - insurance - deductions) wait.
 *
 * net_payable = base + allowances - deductions - tax - insurance (DB generated).
 * Entry:
 *   Dr Expense (base + allowances)
 *   Cr Tax
 *   Cr Insurance
 *   Cr Other deductions clearing (deductions_total) using 2220 if present else fold into cash
 *   Cr Cash (net_payable)
 * Must balance: expense = tax + insurance + deductions + net.
 */
class HrPayrollAccountingService
{
    public const CODE_SALARY_EXPENSE = '6100';
    public const CODE_TAX_PAYABLE = '2200';
    public const CODE_INSURANCE_PAYABLE = '2210';
    public const CODE_DEDUCTIONS_CLEARING = '2220';
    public const CODE_CASH = '1100';

    public function __construct(
        private readonly VoucherPostingService $voucherPosting,
    ) {
    }

    public function postDisbursement(PayrollRecord $payroll): ?string
    {
        $expense = round((float) $payroll->base_salary + (float) $payroll->allowances_total, 4);
        $tax = round((float) $payroll->tax_withheld, 4);
        $insurance = round((float) $payroll->insurance_premium, 4);
        $deductions = round((float) $payroll->deductions_total, 4);
        $net = round((float) $payroll->net_payable, 4);

        if ($expense <= 0) {
            return null;
        }

        $accounts = $this->resolveAccounts($payroll->tenant_id);
        if ($accounts === null) {
            Log::warning('HrPayrollAccountingService: required GL accounts missing; skip voucher.', [
                'payroll_id' => $payroll->payroll_id,
            ]);

            return null;
        }

        $lines = [
            [
                'account_id'  => $accounts['expense'],
                'debit'       => $expense,
                'credit'      => 0,
                'description' => 'Payroll expense',
            ],
        ];

        if ($tax > 0) {
            $lines[] = [
                'account_id'  => $accounts['tax'],
                'debit'       => 0,
                'credit'      => $tax,
                'description' => 'Tax withheld',
            ];
        }
        if ($insurance > 0) {
            $lines[] = [
                'account_id'  => $accounts['insurance'],
                'debit'       => 0,
                'credit'      => $insurance,
                'description' => 'Insurance premium',
            ];
        }
        if ($deductions > 0) {
            $lines[] = [
                'account_id'  => $accounts['deductions'],
                'debit'       => 0,
                'credit'      => $deductions,
                'description' => 'Other deductions',
            ];
        }
        if ($net > 0) {
            $lines[] = [
                'account_id'  => $accounts['cash'],
                'debit'       => 0,
                'credit'      => $net,
                'description' => 'Net pay disbursed',
            ];
        }

        $header = [
            'voucher_date'       => now()->toDateString(),
            'description'        => 'Payroll disbursement ' . $payroll->payroll_id,
            'reference_number'   => 'PAY-' . substr($payroll->payroll_id, 0, 8),
            'status'             => 2,
            'source_module'      => 'hr',
            'source_document_id' => $payroll->payroll_id,
        ];

        return $this->voucherPosting->postVoucher($header, $lines);
    }

    /**
     * @return array{expense:string,tax:string,insurance:string,deductions:string,cash:string}|null
     */
    private function resolveAccounts(string $tenantId): ?array
    {
        $needed = [
            self::CODE_SALARY_EXPENSE,
            self::CODE_TAX_PAYABLE,
            self::CODE_INSURANCE_PAYABLE,
            self::CODE_DEDUCTIONS_CLEARING,
            self::CODE_CASH,
        ];

        $rows = DB::table('fin_accounts')
            ->where('tenant_id', $tenantId)
            ->whereIn('code', $needed)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->get(['account_id', 'code']);

        if ($rows->count() < count($needed)) {
            return null;
        }

        $byCode = $rows->keyBy('code');

        return [
            'expense'    => $byCode[self::CODE_SALARY_EXPENSE]->account_id,
            'tax'        => $byCode[self::CODE_TAX_PAYABLE]->account_id,
            'insurance'  => $byCode[self::CODE_INSURANCE_PAYABLE]->account_id,
            'deductions' => $byCode[self::CODE_DEDUCTIONS_CLEARING]->account_id,
            'cash'       => $byCode[self::CODE_CASH]->account_id,
        ];
    }
}
