<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;

class IdentityCoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_under_tenant_context(): void
    {
        $tenantId = '11111111-1111-4111-8111-111111111111';

        DB::table('tenants')->insert([
            'tenant_id' => $tenantId,
            'tenant_code' => 'TENANT-001',
            'tenant_name' => 'Tenant One',
            'slug' => 'tenant-one',
            'status' => 1,
        ]);

        // ✅ مسیر دقیق بر اساس خروجی route:list
        $response = $this->withHeader('X-Tenant-ID', $tenantId)
            ->postJson('/api/identity-core/identity/register', [
                'first_name' => 'Ali',
                'last_name' => 'Rezaei',
                'mobile' => '09120000000',
                'email' => 'ali@example.com',
                'password' => 'Password123!',
                'is_owner' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.first_name', 'Ali')
            ->assertJsonPath('data.last_name', 'Rezaei');

        $this->assertDatabaseHas('users', ['first_name' => 'Ali']);
        $this->assertDatabaseHas('tenant_users', ['tenant_id' => $tenantId]);
    }
}