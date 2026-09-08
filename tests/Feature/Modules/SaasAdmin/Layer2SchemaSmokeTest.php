<?php

namespace Tests\Feature\Modules\SaasAdmin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class Layer2SchemaSmokeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function all_layer2_tables_exist(): void
    {
        $tables = [
            'admin_users',
            'admin_roles',
            'admin_permissions',
            'admin_user_roles',
            'admin_role_permissions',
            'audit_logs',
            'system_settings',
            'notifications',
            'support_tickets',
            'admin_user_sessions',
            'admin_login_attempts',
            'admin_api_keys',
            'admin_webhooks',
            'notification_templates',
            'notification_deliveries',
            'support_ticket_messages',
            'support_ticket_attachments',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(
                Schema::hasTable($table),
                "Expected Layer 2 table [{$table}] to exist."
            );
        }
    }
}
