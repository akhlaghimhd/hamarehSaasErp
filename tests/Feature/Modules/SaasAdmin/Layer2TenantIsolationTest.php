<?php

namespace Tests\Feature\Modules\SaasAdmin;

use App\Base\Context\TenantContext;
use App\Modules\SaasAdmin\Models\Notification;
use App\Modules\SaasAdmin\Models\SupportTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * L2-M03 – Tenant Isolation / Data Bleeding prevention for Layer 2 tenant-scoped tables.
 */
class Layer2TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantA;
    private string $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = (string) Str::uuid();
        $this->tenantB = (string) Str::uuid();
    }

    #[Test]
    public function notifications_are_isolated_by_tenant(): void
    {
        // Create under Tenant A
        TenantContext::getInstance()->setTenantId($this->tenantA);

        $notificationA = Notification::create([
            'tenant_id'         => $this->tenantA,
            'recipient_user_id' => (string) Str::uuid(),
            'title'             => 'Tenant A Notification',
            'body'              => 'Body A',
            'type_code'         => 'INFO',
            'is_read'           => false,
        ]);

        // Switch to Tenant B
        TenantContext::getInstance()->setTenantId($this->tenantB);

        $notificationB = Notification::create([
            'tenant_id'         => $this->tenantB,
            'recipient_user_id' => (string) Str::uuid(),
            'title'             => 'Tenant B Notification',
            'body'              => 'Body B',
            'type_code'         => 'INFO',
            'is_read'           => false,
        ]);

        // From Tenant B context we must not see Tenant A data
        $visible = Notification::all();

        $this->assertTrue($visible->contains('notification_id', $notificationB->notification_id));
        $this->assertFalse($visible->contains('notification_id', $notificationA->notification_id));

        // Switch back to Tenant A
        TenantContext::getInstance()->setTenantId($this->tenantA);

        $visibleA = Notification::all();

        $this->assertTrue($visibleA->contains('notification_id', $notificationA->notification_id));
        $this->assertFalse($visibleA->contains('notification_id', $notificationB->notification_id));
    }

    #[Test]
    public function support_tickets_are_isolated_by_tenant(): void
    {
        TenantContext::getInstance()->setTenantId($this->tenantA);

        $ticketA = SupportTicket::create([
            'tenant_id'      => $this->tenantA,
            'ticket_number'  => 'TKT-A-001',
            'subject'        => 'Issue from Tenant A',
            'priority'       => 2,
            'status'         => 1,
            'channel'        => 'PORTAL',
        ]);

        TenantContext::getInstance()->setTenantId($this->tenantB);

        $ticketB = SupportTicket::create([
            'tenant_id'      => $this->tenantB,
            'ticket_number'  => 'TKT-B-001',
            'subject'        => 'Issue from Tenant B',
            'priority'       => 2,
            'status'         => 1,
            'channel'        => 'PORTAL',
        ]);

        $visible = SupportTicket::all();

        $this->assertTrue($visible->contains('ticket_id', $ticketB->ticket_id));
        $this->assertFalse($visible->contains('ticket_id', $ticketA->ticket_id));

        TenantContext::getInstance()->setTenantId($this->tenantA);

        $visibleA = SupportTicket::all();

        $this->assertTrue($visibleA->contains('ticket_id', $ticketA->ticket_id));
        $this->assertFalse($visibleA->contains('ticket_id', $ticketB->ticket_id));
    }
}
