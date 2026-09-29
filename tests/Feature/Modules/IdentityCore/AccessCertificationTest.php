<?php

namespace Tests\Feature\Modules\IdentityCore;

use App\Modules\IdentityCore\Models\TenantAccessCertItem;
use App\Modules\IdentityCore\Models\TenantUser;
use App\Modules\IdentityCore\Models\User;
use App\Modules\IdentityCore\Services\AccessCertificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AccessCertificationTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantId;

    private User $user;

    private string $tenantUserId;

    private AccessCertificationService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = (string) Str::uuid();
        DB::table('tenants')->insert([
            'tenant_id'   => $this->tenantId,
            'tenant_code' => 'CERT1',
            'tenant_name' => 'Cert Tenant',
            'slug'        => 'cert-tenant',
            'status'      => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$this->tenantId]);

        $this->user = User::factory()->create([
            'email'  => 'cert.user@example.com',
            'mobile' => '09121112233',
            'status' => 1,
        ]);

        $this->tenantUserId = (string) Str::uuid();
        TenantUser::create([
            'tenant_user_id' => $this->tenantUserId,
            'tenant_id'      => $this->tenantId,
            'user_id'        => $this->user->user_id,
            'status'         => 1,
            'is_owner'       => true,
        ]);

        $this->svc = app(AccessCertificationService::class);
    }

    #[Test]
    public function create_and_open_generates_items(): void
    {
        $campaign = $this->svc->createCampaign($this->tenantId, 'q1-2026', 'Q1 Review');
        $this->assertSame('DRAFT', $campaign->status);

        $opened = $this->svc->openCampaign($this->tenantId, $campaign->campaign_id, $this->user->user_id);
        $this->assertSame('OPEN', $opened->status);

        $items = $this->svc->listItems($this->tenantId, $campaign->campaign_id);
        $this->assertCount(1, $items);
        $this->assertSame(TenantAccessCertItem::DECISION_PENDING, $items->first()->decision);
    }

    #[Test]
    public function certify_and_complete_campaign(): void
    {
        $campaign = $this->svc->createCampaign($this->tenantId, 'q2-2026', 'Q2 Review');
        $this->svc->openCampaign($this->tenantId, $campaign->campaign_id);

        $item = $this->svc->listItems($this->tenantId, $campaign->campaign_id)->first();

        $this->svc->certifyItem(
            $this->tenantId,
            $item->item_id,
            'APPROVED',
            $this->user->user_id,
            'OK'
        );

        $done = $this->svc->completeCampaign($this->tenantId, $campaign->campaign_id);
        $this->assertSame('COMPLETED', $done->status);

        $summary = $this->svc->campaignSummary($this->tenantId, $campaign->campaign_id);
        $this->assertSame(1, $summary['totals']['approved']);
        $this->assertSame(0, $summary['totals']['pending']);
    }

    #[Test]
    public function cannot_complete_with_pending_items(): void
    {
        $campaign = $this->svc->createCampaign($this->tenantId, 'q3-2026', 'Q3');
        $this->svc->openCampaign($this->tenantId, $campaign->campaign_id);

        $this->expectException(HttpException::class);
        $this->svc->completeCampaign($this->tenantId, $campaign->campaign_id);
    }

    #[Test]
    public function cannot_open_twice(): void
    {
        $campaign = $this->svc->createCampaign($this->tenantId, 'q4-2026', 'Q4');
        $this->svc->openCampaign($this->tenantId, $campaign->campaign_id);

        $this->expectException(HttpException::class);
        $this->svc->openCampaign($this->tenantId, $campaign->campaign_id);
    }
}
