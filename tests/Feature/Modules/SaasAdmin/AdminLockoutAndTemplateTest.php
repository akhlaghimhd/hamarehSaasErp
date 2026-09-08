<?php

namespace Tests\Feature\Modules\SaasAdmin;

use App\Modules\SaasAdmin\Models\AdminUser;
use App\Modules\SaasAdmin\Services\AdminAuthService;
use App\Modules\SaasAdmin\Services\AdminUserService;
use App\Modules\SaasAdmin\Services\NotificationTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminLockoutAndTemplateTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_locks_account_after_five_failed_logins(): void
    {
        app(AdminUserService::class)->create('lockme', 'lock@example.com', 'SecurePass123!');
        $auth = app(AdminAuthService::class);

        for ($i = 0; $i < 5; $i++) {
            try {
                $auth->login('lockme', 'wrong-pass', '1.2.3.4');
            } catch (\InvalidArgumentException) {
                // expected
            }
        }

        $user = AdminUser::query()->where('username', 'lockme')->first();
        $this->assertNotNull($user->locked_until);
        $this->assertTrue($user->locked_until->isFuture());

        $this->expectException(\InvalidArgumentException::class);
        $auth->login('lockme', 'SecurePass123!', '1.2.3.4');
    }

    #[Test]
    public function it_upserts_notification_template(): void
    {
        $service = app(NotificationTemplateService::class);

        $t = $service->upsert(
            'WELCOME_EMAIL',
            'Welcome',
            'Hello {{name}}',
            'EMAIL'
        );

        $this->assertEquals('WELCOME_EMAIL', $t->template_code);

        $updated = $service->upsert(
            'WELCOME_EMAIL',
            'Welcome Updated',
            'Hi {{name}}',
            'EMAIL'
        );

        $this->assertEquals('Welcome Updated', $updated->title);
        $this->assertSame(1, $service->list()->count());
    }
}
