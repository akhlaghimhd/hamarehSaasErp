<?php

namespace Tests\Feature\Modules\SaasAdmin;

use App\Modules\SaasAdmin\Models\AdminUser;
use App\Modules\SaasAdmin\Services\AdminAuthService;
use App\Modules\SaasAdmin\Services\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuditLogWriteTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function successful_admin_login_writes_audit_log(): void
    {
        $adminId = (string) Str::uuid();
        AdminUser::query()->create([
            'admin_user_id'      => $adminId,
            'username'           => 'audit.admin',
            'email'              => 'audit.admin@platform.local',
            'password_hash'      => Hash::make('LocalAdmin1!'),
            'first_name'         => 'Audit',
            'last_name'          => 'Admin',
            'status'             => 1,
            'failed_login_count' => 0,
            'two_factor_enabled' => false,
            'row_version'        => 1,
        ]);

        /** @var AdminAuthService $auth */
        $auth = app(AdminAuthService::class);
        $result = $auth->login('audit.admin', 'LocalAdmin1!', '127.0.0.1', 'PHPUnit');

        $this->assertNotEmpty($result['token']);

        $this->assertDatabaseHas('audit_logs', [
            'entity_name'   => 'admin_auth',
            'action_type'   => 'LOGIN',
            'admin_user_id' => $adminId,
        ]);
    }

    #[Test]
    public function failed_admin_login_writes_audit_log(): void
    {
        $adminId = (string) Str::uuid();
        AdminUser::query()->create([
            'admin_user_id'      => $adminId,
            'username'           => 'audit.fail',
            'email'              => 'audit.fail@platform.local',
            'password_hash'      => Hash::make('LocalAdmin1!'),
            'status'             => 1,
            'failed_login_count' => 0,
            'two_factor_enabled' => false,
            'row_version'        => 1,
        ]);

        /** @var AdminAuthService $auth */
        $auth = app(AdminAuthService::class);

        try {
            $auth->login('audit.fail', 'WrongPassword!!', '10.0.0.1', 'PHPUnit');
            $this->fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException) {
            // expected
        }

        $this->assertDatabaseHas('audit_logs', [
            'entity_name' => 'admin_auth',
            'action_type' => 'LOGIN_FAILED',
        ]);
    }

    #[Test]
    public function audit_service_writes_entitlement_style_row(): void
    {
        $tenantId = (string) Str::uuid();
        $adminId = (string) Str::uuid();

        /** @var AuditLogService $audit */
        $audit = app(AuditLogService::class);
        $log = $audit->write(
            entityName: 'tenant_feature_entitlements',
            actionType: 'GRANT',
            entityId: (string) Str::uuid(),
            tenantId: $tenantId,
            adminUserId: $adminId,
            oldValues: ['is_enabled' => false],
            newValues: ['feature_code' => 'multi_company', 'is_enabled' => true],
            severity: 2,
            createdBy: $adminId
        );

        $this->assertNotNull($log->audit_log_id);
        $this->assertDatabaseHas('audit_logs', [
            'entity_name'   => 'tenant_feature_entitlements',
            'action_type'   => 'GRANT',
            'tenant_id'     => $tenantId,
            'admin_user_id' => $adminId,
        ]);

        $list = $audit->list(tenantId: $tenantId, entityName: 'tenant_feature_entitlements');
        $this->assertTrue($list->contains(fn ($r) => $r->action_type === 'GRANT'));
    }
}
