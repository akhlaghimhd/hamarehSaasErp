<?php

namespace Tests\Feature\Modules\HrManagement;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\HrManagement\Services\EmployeeService;
use App\Modules\HrManagement\Services\EmployeeProfileService;
use App\Modules\HrManagement\Services\PayrollRecordService;
use App\Modules\HrManagement\Services\HrDocumentService;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L6-HR-02 — Profile upsert, payroll create/disburse, HR document
 */
class ProfilePayrollDocumentTest extends TestCase
{
    protected Tenant $tenantA;
    protected User $userA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantA = Tenant::factory()->create(['tenant_code' => 'HR_PAY_A', 'status' => 1]);
        $this->userA = User::factory()->create(['status' => 1]);

        TenantContext::getInstance()->setTenantId($this->tenantA->tenant_id);
        Context::add('tenant_id', $this->tenantA->tenant_id);
        Context::add('user_id', $this->userA->user_id);
        app()->instance('current_tenant_id', $this->tenantA->tenant_id);
        ScopeContext::resetInstance();
    }

    protected function tearDown(): void
    {
        ScopeContext::resetInstance();
        TenantContext::resetInstance();
        parent::tearDown();
    }

    #[Test]
    public function profile_payroll_and_document_happy_path(): void
    {
        $emp = app(EmployeeService::class)->create([
            'employee_code' => 'E-2001',
            'hire_date'     => '2026-02-01',
            'job_title'     => 'Accountant',
        ]);

        $profile = app(EmployeeProfileService::class)->upsert($emp->employee_id, [
            'national_code'  => '0012345678',
            'gender'         => 1,
            'marital_status' => 1,
            'birth_date'     => '1990-05-10',
        ]);
        $this->assertSame('0012345678', $profile->national_code);

        $profile2 = app(EmployeeProfileService::class)->upsert($emp->employee_id, [
            'national_code'  => '0012345678',
            'father_name'    => 'Ali',
            'gender'         => 1,
            'marital_status' => 2,
        ]);
        $this->assertSame('Ali', $profile2->father_name);
        $this->assertSame(2, (int) $profile2->marital_status);

        $fiscalPeriodId = (string) Str::uuid();
        $payroll = app(PayrollRecordService::class)->create([
            'employee_id'       => $emp->employee_id,
            'fiscal_period_id'  => $fiscalPeriodId,
            'base_salary'       => 10000000,
            'allowances_total'  => 500000,
            'deductions_total'  => 200000,
            'tax_withheld'      => 100000,
            'insurance_premium' => 300000,
        ]);

        // net = 10_000_000 + 500_000 - 200_000 - 100_000 - 300_000 = 9_900_000
        $this->assertEquals(9900000.0, (float) $payroll->net_payable);
        $this->assertFalse($payroll->is_disbursed);

        $disbursed = app(PayrollRecordService::class)->markDisbursed(
            $payroll->payroll_id,
            (string) Str::uuid()
        );
        $this->assertTrue($disbursed->is_disbursed);
        $this->assertNotNull($disbursed->disbursed_at);

        $doc = app(HrDocumentService::class)->create([
            'employee_id'        => $emp->employee_id,
            'document_type_code' => 'CONTRACT',
            'document_title'     => 'Employment Contract 2026',
            'issue_date'         => '2026-02-01',
        ]);
        $this->assertSame(HrDocumentService::STATUS_VALID, (int) $doc->status);
        $this->assertSame('CONTRACT', $doc->document_type_code);
    }
}
