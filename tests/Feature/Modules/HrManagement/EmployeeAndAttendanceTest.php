<?php

namespace Tests\Feature\Modules\HrManagement;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use App\Modules\IdentityCore\Models\User;
use App\Modules\HrManagement\Models\Employee;
use App\Modules\HrManagement\Services\EmployeeService;
use App\Modules\HrManagement\Services\AttendanceRecordService;
use App\Base\Context\TenantContext;
use App\Base\Context\ScopeContext;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

/**
 * L6-HR-01 — Employee CRUD + attendance clock in/out + isolation
 */
class EmployeeAndAttendanceTest extends TestCase
{
    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantA = Tenant::factory()->create(['tenant_code' => 'HR_A', 'status' => 1]);
        $this->tenantB = Tenant::factory()->create(['tenant_code' => 'HR_B', 'status' => 1]);
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
    public function create_employee_clock_in_out_and_terminate(): void
    {
        $empService = app(EmployeeService::class);
        $attService = app(AttendanceRecordService::class);

        $emp = $empService->create([
            'employee_code'   => 'E-1001',
            'hire_date'       => '2026-01-15',
            'employment_type' => EmployeeService::TYPE_FULL_TIME,
            'job_title'       => 'Operator',
        ]);

        $this->assertSame(EmployeeService::STATUS_ACTIVE, (int) $emp->status);
        $this->assertSame('E-1001', $emp->employee_code);

        $in = $attService->clockIn($emp->employee_id, '2026-09-07');
        $this->assertNotNull($in->clock_in);
        $this->assertNull($in->clock_out);
        $this->assertSame(AttendanceRecordService::STATUS_PRESENT, (int) $in->status);

        $out = $attService->clockOut($emp->employee_id, '2026-09-07', 1.5);
        $this->assertNotNull($out->clock_out);
        $this->assertEquals(1.5, (float) $out->overtime_hours);

        $terminated = $empService->terminate($emp->employee_id, '2026-09-07');
        $this->assertSame(EmployeeService::STATUS_TERMINATED, (int) $terminated->status);
        $this->assertSame('2026-09-07', $terminated->termination_date->toDateString());
    }

    #[Test]
    public function employee_is_tenant_isolated(): void
    {
        $empId = (string) Str::uuid();
        Employee::withoutGlobalScopes()->create([
            'employee_id'     => $empId,
            'tenant_id'       => $this->tenantA->tenant_id,
            'employee_code'   => 'ISO-1',
            'employment_type' => 1,
            'hire_date'       => '2026-01-01',
            'status'          => 1,
            'row_version'     => 1,
        ]);

        $cross = Employee::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantB->tenant_id)
            ->where('employee_id', $empId)
            ->first();

        $this->assertNull($cross);
    }
}
