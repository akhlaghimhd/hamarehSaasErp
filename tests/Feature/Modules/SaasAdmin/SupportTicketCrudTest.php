<?php

namespace Tests\Feature\Modules\SaasAdmin;

use App\Modules\SaasAdmin\Services\SupportTicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SupportTicketCrudTest extends TestCase
{
    use RefreshDatabase;

    private SupportTicketService $service;
    private string $tenantId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SupportTicketService::class);
        $this->tenantId = (string) Str::uuid();
    }

    #[Test]
    public function it_creates_support_ticket(): void
    {
        $ticket = $this->service->create(
            tenantId: $this->tenantId,
            subject: 'Cannot login',
            description: 'User reports login failure'
        );

        $this->assertNotNull($ticket->ticket_id);
        $this->assertStringStartsWith('TKT-', $ticket->ticket_number);
        $this->assertEquals(1, $ticket->status);
        $this->assertDatabaseHas('support_tickets', [
            'ticket_id' => $ticket->ticket_id,
            'subject'   => 'Cannot login',
        ]);
    }

    #[Test]
    public function it_updates_support_ticket(): void
    {
        $ticket = $this->service->create($this->tenantId, 'Old subject');

        $updated = $this->service->update(
            $ticket->ticket_id,
            subject: 'New subject',
            priority: 1,
            status: 2
        );

        $this->assertEquals('New subject', $updated->subject);
        $this->assertEquals(1, $updated->priority);
        $this->assertEquals(2, $updated->status);
    }

    #[Test]
    public function it_soft_deletes_support_ticket(): void
    {
        $ticket = $this->service->create($this->tenantId, 'To delete');

        $this->service->softDelete($ticket->ticket_id);

        $this->assertSoftDeleted('support_tickets', [
            'ticket_id' => $ticket->ticket_id,
        ]);
    }

    #[Test]
    public function it_lists_tickets_by_tenant(): void
    {
        $this->service->create($this->tenantId, 'T1');
        $this->service->create($this->tenantId, 'T2');

        $list = $this->service->listByTenant($this->tenantId);

        $this->assertGreaterThanOrEqual(2, $list->count());
    }
}
