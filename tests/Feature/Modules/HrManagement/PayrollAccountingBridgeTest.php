<?php

namespace Tests\Feature\Modules\HrManagement;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\HrManagement\Services\EmployeeService;
use App\Modules\HrManagement\Services\PayrollRecordService;
use App\Modules\HrManagement\Services\HrPayrollAccountingService;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L6-HR-03 — disburse posts balanced voucher when GL accounts exist
 */
class PayrollAccountingBridgeTest extends TestCase
{
    protected Tenant $tenantA;
    protected User $userA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantA = Tenant::factory()->create(['tenant_code' => 'HR_ACC_A', 'status' => 1]);
        $this->userA = User::factory()->create(['status' => 1]);

        TenantContext::getInstance()->setTenantId($this->tenantA->tenant_id);
        Context::add('tenant_id', $this->tenantA->tenant_id);
        Context::add('user_id', $this->userA->user_id);
        app()->instance('current_tenant_id', $this->tenantA->tenant_id);
        ScopeContext::resetInstance();

        $this->seedAccounts();
    }

    protected function tearDown(): void
    {
        ScopeContext::resetInstance();
        TenantContext::resetInstance();
        parent::tearDown();
    }

    private function seedAccounts(): void
    {
        $codes = [
            HrPayrollAccountingService::CODE_CASH,
            HrPayrollAccountingService::CODE_TAX_PAYABLE,
            HrPayrollAccountingService::CODE_INSURANCE_PAYABLE,
            HrPayrollAccountingService::CODE_DEDUCTIONS_CLEARING,
            HrPayrollAccountingService::CODE_SALARY_EXPENSE,
        ];
        foreach ($codes as $code) {
            DB::table('fin_accounts')->insert([
                'account_id'  => (string) Str::uuid(),
                'tenant_id'   => $this->tenantA->tenant_id,
                'code'        => $code,
                'name'        => 'Acc ' . $code,
                'account_type'=> 1,
                'is_active'   => true,
                'created_at'  => now(),
                'updated_at'  => now(),
                'row_version' => 1,
            ]);
        }
    }

    #[Test]
    public function disburse_posts_voucher_and_links_journal_entry(): void
    {
        $emp = app(EmployeeService::class)->create([
            'employee_code' => 'E-ACC-1',
            'hire_date'     => '2026-01-01',
        ]);

        $payroll = app(PayrollRecordService::class)->create([
            'employee_id'       => $emp->employee_id,
            'fiscal_period_id'  => (string) Str::uuid(),
            'base_salary'       => 10000000,
            'allowances_total'  => 500000,
            'deductions_total'  => 200000,
            'tax_withheld'      => 100000,
            'insurance_premium' => 300000,
        ]);

        $disbursed = app(PayrollRecordService::class)->markDisbursed($payroll->payroll_id);

        $this->assertTrue($disbursed->is_disbursed);
        $this->assertNotEmpty($disbursed->journal_entry_id);

        $voucher = DB::table('fin_vouchers')
            ->where('voucher_id', $disbursed->journal_entry_id)
            ->where('tenant_id', $this->tenantA->tenant_id)
            ->first();
        $this->assertNotNull($voucher);
        // expense = 10_500_000
        $this->assertEquals(10500000.0, (float) $voucher->total_amount);
    }

    #[Test]
    public function disburse_skips_voucher_when_accounts_missing(): void
    {
        DB::table('fin_accounts')->where('tenant_id', $this->tenantA->tenant_id)->delete();

        $emp = app(EmployeeService::class)->create([
            'employee_code' => 'E-ACC-2',
            'hire_date'     => '2026-01-01',
        ]);

        $payroll = app(PayrollRecordService::class)->create([
            'employee_id'      => $emp->employee_id,
            'fiscal_period_id' => (string) Str::uuid(),
            'base_salary'      => 1000,
        ]);

        $disbursed = app(PayrollRecordService::class)->markDisbursed($payroll->payroll_id);
        $this->assertTrue($disbursed->is_disbursed);
        $this->assertNull($disbursed->journal_entry_id);
    }
}
