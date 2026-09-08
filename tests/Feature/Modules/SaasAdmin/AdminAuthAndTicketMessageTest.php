<?php

namespace Tests\Feature\Modules\SaasAdmin;

use App\Modules\SaasAdmin\Models\AdminUser;
use App\Modules\SaasAdmin\Services\AdminAuthService;
use App\Modules\SaasAdmin\Services\AdminUserService;
use App\Modules\SaasAdmin\Services\SupportTicketMessageService;
use App\Modules\SaasAdmin\Services\SupportTicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminAuthAndTicketMessageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_logs_in_admin_and_creates_session(): void
    {
        app(AdminUserService::class)->create(
            'authadmin',
            'auth@example.com',
            'SecurePass123!'
        );

        $result = app(AdminAuthService::class)->login(
            'authadmin',
            'SecurePass123!',
            '127.0.0.1',
            'PHPUnit'
        );

        $this->assertArrayHasKey('token', $result);
        $this->assertTrue($result['session']->is_active);
        $this->assertDatabaseHas('admin_login_attempts', [
            'username'      => 'authadmin',
            'is_successful' => true,
        ]);
    }

    #[Test]
    public function it_rejects_bad_password_and_records_attempt(): void
    {
        app(AdminUserService::class)->create(
            'badpass',
            'bad@example.com',
            'SecurePass123!'
        );

        $this->expectException(\InvalidArgumentException::class);

        try {
            app(AdminAuthService::class)->login('badpass', 'wrong-password', '10.0.0.1');
        } finally {
            $this->assertDatabaseHas('admin_login_attempts', [
                'username'      => 'badpass',
                'is_successful' => false,
            ]);
        }
    }

    #[Test]
    public function it_logs_out_and_deactivates_session(): void
    {
        app(AdminUserService::class)->create('logoutu', 'lo@example.com', 'SecurePass123!');
        $auth = app(AdminAuthService::class);
        $result = $auth->login('logoutu', 'SecurePass123!', '127.0.0.1');

        $auth->logout($result['token']);

        $this->assertDatabaseHas('admin_user_sessions', [
            'session_id' => $result['session']->session_id,
            'is_active'  => false,
        ]);
    }

    #[Test]
    public function it_adds_ticket_message_and_attachment(): void
    {
        $tenantId = (string) Str::uuid();
        $ticket = app(SupportTicketService::class)->create($tenantId, 'Need help');

        $msgService = app(SupportTicketMessageService::class);
        $msg = $msgService->addMessage(
            $ticket->ticket_id,
            2,
            (string) Str::uuid(),
            'We are looking into it.'
        );

        $att = $msgService->addAttachment(
            $msg->message_id,
            'screenshot.png',
            's3://bucket/screenshot.png',
            2048
        );

        $this->assertNotNull($msg->message_id);
        $this->assertEquals(2048, $att->file_size_bytes);

        $list = $msgService->listByTicket($ticket->ticket_id);
        $this->assertCount(1, $list);
    }
}
