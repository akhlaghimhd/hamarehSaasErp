<?php

namespace Tests\Feature\Modules\IdentityCore;

use App\Modules\IdentityCore\Models\TenantMembershipHistory;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Services\MembershipHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JoinerMoverLeaverTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantId;

    private string $tenantUserId;

    private MembershipHistoryService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = (string) Str::uuid();
        DB::table('tenants')->insert([
            'tenant_id'   => $this->tenantId,
            'tenant_code' => 'JML1',
            'tenant_name' => 'JML Tenant',
            'slug'        => 'jml-tenant',
            'status'      => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$this->tenantId]);
        app()->instance('current_tenant_id', $this->tenantId);

        $user = User::factory()->create([
            'email'  => 'jml.user@example.com',
            'mobile' => '09125556677',
            'status' => 1,
        ]);

        $this->tenantUserId = (string) Str::uuid();
        TenantUser::create([
            'tenant_user_id' => $this->tenantUserId,
            'tenant_id'      => $this->tenantId,
            'user_id'        => $user->user_id,
            'status'         => 1,
            'is_owner'       => false,
        ]);

        $this->svc = app(MembershipHistoryService::class);
    }

    #[Test]
    public function record_joiner_writes_history_and_event(): void
    {
        $h = $this->svc->recordJoiner($this->tenantUserId, 'Onboarding batch A');

        $this->assertSame(MembershipHistoryService::REASON_JOINER, $h->reason_code);
        $this->assertSame(1, $h->new_status);
        $this->assertNull($h->previous_status);

        $this->assertTrue(
            DB::table('event_outbox')
                ->where('tenant_id', $this->tenantId)
                ->where('event_type', 'identity.membership.joiner.v1')
                ->exists()
        );
    }

    #[Test]
    public function record_mover_keeps_status_and_emits_mover_event(): void
    {
        $h = $this->svc->recordMover($this->tenantUserId, 1, 'Moved to Finance branch');

        $this->assertSame(MembershipHistoryService::REASON_MOVER, $h->reason_code);
        $this->assertSame(1, $h->previous_status);
        $this->assertSame(1, $h->new_status);

        $this->assertTrue(
            DB::table('event_outbox')
                ->where('event_type', 'identity.membership.mover.v1')
                ->exists()
        );
    }

    #[Test]
    public function record_leaver_sets_inactive_and_emits_leaver_event(): void
    {
        $h = $this->svc->recordLeaver($this->tenantUserId, 1, 'Resignation');

        $this->assertSame(MembershipHistoryService::REASON_LEAVER, $h->reason_code);
        $this->assertSame(0, $h->new_status);

        $this->assertTrue(
            DB::table('event_outbox')
                ->where('event_type', 'identity.membership.leaver.v1')
                ->exists()
        );
    }

    #[Test]
    public function list_filters_by_reason_code(): void
    {
        $this->svc->recordJoiner($this->tenantUserId);
        $this->svc->recordMover($this->tenantUserId);
        $this->svc->recordLeaver($this->tenantUserId);

        $joiners = $this->svc->listForTenant(null, 100, MembershipHistoryService::REASON_JOINER);
        $this->assertCount(1, $joiners);
        $this->assertSame('JOINER', $joiners->first()->reason_code);

        $all = $this->svc->listForTenant();
        $this->assertGreaterThanOrEqual(3, $all->count());
    }

    #[Test]
    public function suspend_and_reactivate_lifecycle(): void
    {
        $s = $this->svc->recordSuspend($this->tenantUserId);
        $this->assertSame(MembershipHistoryService::REASON_SUSPEND, $s->reason_code);
        $this->assertSame(2, $s->new_status);

        $r = $this->svc->recordReactivate($this->tenantUserId);
        $this->assertSame(MembershipHistoryService::REASON_REACTIVATE, $r->reason_code);
        $this->assertSame(1, $r->new_status);

        $this->assertTrue(
            DB::table('event_outbox')->where('event_type', 'identity.membership.suspended.v1')->exists()
        );
        $this->assertTrue(
            DB::table('event_outbox')->where('event_type', 'identity.membership.reactivated.v1')->exists()
        );
    }
}
