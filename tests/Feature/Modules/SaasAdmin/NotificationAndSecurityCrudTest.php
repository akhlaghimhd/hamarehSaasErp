<?php

namespace Tests\Feature\Modules\SaasAdmin;

use App\Modules\SaasAdmin\Services\AdminApiKeyService;
use App\Modules\SaasAdmin\Services\AdminWebhookService;
use App\Modules\SaasAdmin\Services\AuditLogService;
use App\Modules\SaasAdmin\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationAndSecurityCrudTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_and_marks_notification_read(): void
    {
        $service = app(NotificationService::class);
        $tenantId = (string) Str::uuid();
        $userId = (string) Str::uuid();

        $n = $service->create($tenantId, $userId, 'Hello', 'Body text', 'INFO');

        $this->assertFalse($n->is_read);

        $read = $service->markRead($n->notification_id);

        $this->assertTrue($read->is_read);
        $this->assertNotNull($read->read_at);
    }

    #[Test]
    public function it_creates_and_revokes_api_key(): void
    {
        $service = app(AdminApiKeyService::class);
        $adminId = (string) Str::uuid();

        $result = $service->create($adminId, 'CI Key');

        $this->assertArrayHasKey('plain_key', $result);
        $this->assertStringStartsWith('sak_', $result['plain_key']);
        $this->assertTrue($result['model']->is_active);

        $service->revoke($result['model']->api_key_id);

        $this->assertDatabaseHas('admin_api_keys', [
            'api_key_id' => $result['model']->api_key_id,
            'is_active'  => false,
        ]);
    }

    #[Test]
    public function it_creates_webhook(): void
    {
        $service = app(AdminWebhookService::class);

        $wh = $service->create(
            'Outbox Hook',
            'https://example.com/hooks',
            ['SaasAdmin.AdminUserCreated.v1']
        );

        $this->assertNotNull($wh->webhook_id);
        $this->assertTrue($wh->is_active);
        $this->assertContains('SaasAdmin.AdminUserCreated.v1', $wh->event_types);
    }

    #[Test]
    public function it_writes_and_lists_audit_log(): void
    {
        $service = app(AuditLogService::class);

        $log = $service->write(
            entityName: 'admin_users',
            actionType: 'CREATE',
            entityId: (string) Str::uuid(),
            severity: 1
        );

        $this->assertNotNull($log->audit_log_id);

        $list = $service->list(entityName: 'admin_users');

        $this->assertGreaterThanOrEqual(1, $list->count());
    }
}
