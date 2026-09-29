<?php

namespace Tests\Feature\Modules\IdentityCore;

use App\Modules\IdentityCore\Services\IdentityAuditExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class IdentityAuditExportTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantId;

    private IdentityAuditExportService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = (string) Str::uuid();
        DB::table('tenants')->insert([
            'tenant_id'   => $this->tenantId,
            'tenant_code' => 'AUD1',
            'tenant_name' => 'Audit Tenant',
            'slug'        => 'aud-tenant',
            'status'      => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$this->tenantId]);
        app()->instance('current_tenant_id', $this->tenantId);

        $this->svc = app(IdentityAuditExportService::class);
    }

    #[Test]
    public function export_returns_envelope_with_sections(): void
    {
        $out = $this->svc->export($this->tenantId, [
            IdentityAuditExportService::SECTION_USER_ROLES,
            IdentityAuditExportService::SECTION_MEMBERSHIP,
        ]);

        $this->assertSame($this->tenantId, $out['tenant_id']);
        $this->assertArrayHasKey('exported_at', $out);
        $this->assertArrayHasKey('sections', $out);
        $this->assertArrayHasKey(IdentityAuditExportService::SECTION_USER_ROLES, $out['sections']);
        $this->assertArrayHasKey(IdentityAuditExportService::SECTION_MEMBERSHIP, $out['sections']);
    }

    #[Test]
    public function export_includes_membership_rows(): void
    {
        $userId = (string) Str::uuid();
        $tuId = (string) Str::uuid();

        DB::table('users')->insert([
            'user_id'     => $userId,
            'email'       => 'aud@example.com',
            'mobile'      => '09123334455',
            'user_kind'   => 1,
            'status'      => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
            'row_version' => 1,
        ]);

        DB::table('tenant_users')->insert([
            'tenant_user_id' => $tuId,
            'tenant_id'      => $this->tenantId,
            'user_id'        => $userId,
            'status'         => 1,
            'is_owner'       => false,
            'created_at'     => now(),
            'updated_at'     => now(),
            'row_version'    => 1,
        ]);

        DB::table('tenant_membership_histories')->insert([
            'history_id'      => (string) Str::uuid(),
            'tenant_id'       => $this->tenantId,
            'tenant_user_id'  => $tuId,
            'previous_status' => null,
            'new_status'      => 1,
            'reason_code'     => 'JOINER',
            'description'     => 'join',
            'effective_date'  => now(),
            'created_at'      => now(),
            'updated_at'      => now(),
            'row_version'     => 1,
        ]);

        $out = $this->svc->export($this->tenantId, [
            IdentityAuditExportService::SECTION_MEMBERSHIP,
        ]);

        $rows = $out['sections'][IdentityAuditExportService::SECTION_MEMBERSHIP];
        $this->assertNotEmpty($rows);
        $this->assertSame('JOINER', $rows[0]['reason_code']);
    }

    #[Test]
    public function invalid_sections_rejected(): void
    {
        $this->expectException(HttpException::class);
        $this->svc->export($this->tenantId, ['not_a_real_section']);
    }

    #[Test]
    public function empty_tenant_rejected(): void
    {
        $this->expectException(HttpException::class);
        $this->svc->export('');
    }
}
