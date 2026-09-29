<?php

namespace Tests\Feature\Modules\IdentityCore;

use App\Modules\IdentityCore\Services\IdentitySessionReevaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IdentitySessionReevaluationTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantId;

    private string $userId;

    private string $tenantUserId;

    private IdentitySessionReevaluationService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = (string) Str::uuid();
        $this->userId = (string) Str::uuid();
        $this->tenantUserId = (string) Str::uuid();

        DB::table('tenants')->insert([
            'tenant_id'   => $this->tenantId,
            'tenant_code' => 'REE1',
            'tenant_name' => 'Reeval Tenant',
            'slug'        => 'ree-tenant',
            'status'      => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        DB::table('users')->insert([
            'user_id'     => $this->userId,
            'email'       => 'ree@example.com',
            'mobile'      => '09124445566',
            'user_kind'   => 1,
            'status'      => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
            'row_version' => 1,
        ]);

        DB::table('tenant_users')->insert([
            'tenant_user_id' => $this->tenantUserId,
            'tenant_id'      => $this->tenantId,
            'user_id'        => $this->userId,
            'status'         => 1,
            'is_owner'       => false,
            'access_version' => 1,
            'created_at'     => now(),
            'updated_at'     => now(),
            'row_version'    => 1,
        ]);

        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$this->tenantId]);

        $this->svc = app(IdentitySessionReevaluationService::class);
    }

    #[Test]
    public function invalidate_bumps_access_version(): void
    {
        $next = $this->svc->invalidateUser($this->tenantId, $this->userId, 'role_changed');

        $this->assertSame(2, $next);
        $this->assertSame(2, $this->svc->currentVersion($this->tenantId, $this->userId));
    }

    #[Test]
    public function is_stale_when_session_behind(): void
    {
        $this->svc->invalidateUser($this->tenantId, $this->userId);
        $this->svc->invalidateUser($this->tenantId, $this->userId);

        $this->assertTrue($this->svc->isStale($this->tenantId, $this->userId, 1));
        $this->assertFalse($this->svc->isStale($this->tenantId, $this->userId, 3));
        $this->assertFalse($this->svc->isStale($this->tenantId, $this->userId, null));
    }

    #[Test]
    public function invalidate_emits_outbox_event(): void
    {
        $this->svc->invalidateUser($this->tenantId, $this->userId, 'test');

        $this->assertTrue(
            DB::table('event_outbox')
                ->where('tenant_id', $this->tenantId)
                ->where('event_type', 'identity.access.reevaluated.v1')
                ->exists()
        );
    }

    #[Test]
    public function missing_membership_returns_null(): void
    {
        $v = $this->svc->invalidateUser($this->tenantId, (string) Str::uuid());
        $this->assertNull($v);
    }
}
