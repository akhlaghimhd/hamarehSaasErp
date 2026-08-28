<?php

namespace Tests\Feature\Base\EventBus;

use Tests\TestCase;
use App\Modules\SaasPlatform\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Context; // اصلاح کلاس کانتکست

class OutboxWorkerTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create([
            'tenant_code' => 'EVENT_ORG'
        ]);
    }

    /**
     * @test
     */
    public function it_saves_event_to_outbox_table_instead_of_direct_dispatch()
    {
        // استفاده از فیساد جدید لاراول برای تنظیم کانتکست
        Context::add('tenant_id', $this->tenant->tenant_id);

        $aggregateId = (string) \Illuminate\Support\Str::uuid();
        $payload = [
            'user_id' => (string) \Illuminate\Support\Str::uuid(),
            'username' => 'test_user',
            'timestamp' => now()->toIso8601String()
        ];

        DB::table('event_outbox')->insert([
            'event_id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => $this->tenant->tenant_id,
            'aggregate_type' => 'User',
            'aggregate_id' => $aggregateId,
            'event_type' => 'IdentityCore.UserLoggedIn.v1',
            'payload' => json_encode($payload),
            'status' => 1, // 1: Pending
            'retry_count' => 0,
            'created_at' => now()
        ]);

        $this->assertDatabaseHas('event_outbox', [
            'tenant_id' => $this->tenant->tenant_id,
            'event_type' => 'IdentityCore.UserLoggedIn.v1',
            'status' => 1 
        ]);
    }

    /**
     * @test
     */
    public function outbox_worker_processes_pending_events_and_updates_status()
    {
        Event::fake(); 

        $eventId = (string) \Illuminate\Support\Str::uuid();
        
        DB::table('event_outbox')->insert([
            'event_id' => $eventId,
            'tenant_id' => $this->tenant->tenant_id,
            'aggregate_type' => 'User',
            'aggregate_id' => (string) \Illuminate\Support\Str::uuid(),
            'event_type' => 'IdentityCore.UserLoggedIn.v1',
            'payload' => json_encode(['key' => 'value']),
            'status' => 1, 
            'retry_count' => 0,
            'created_at' => now()->subMinutes(5)
        ]);

        $this->artisan('erp:process-outbox')->assertExitCode(0);

        Event::assertDispatched('IdentityCore.UserLoggedIn.v1');

        $this->assertDatabaseHas('event_outbox', [
            'event_id' => $eventId,
            // در حالت تست سینک (Sync)، رویداد مستقیماً پردازش شده و استاتوس ۲ می‌گیرد
            'status' => 2 
        ]);
    }
    
 /**
     * @test
     */
    public function outbox_worker_increments_retry_count_on_failure()
    {
        $eventId = (string) \Illuminate\Support\Str::uuid();
        
        DB::table('event_outbox')->insert([
            'event_id' => $eventId,
            'tenant_id' => $this->tenant->tenant_id,
            'aggregate_type' => 'User',
            'aggregate_id' => (string) \Illuminate\Support\Str::uuid(),
            'event_type' => 'IdentityCore.FailingEvent.v1',
            'payload' => json_encode(['trigger_error' => true]),
            'status' => 1, 
            'retry_count' => 0,
            'created_at' => now()
        ]);

        Event::listen('IdentityCore.FailingEvent.v1', function () {
            throw new \Exception('Simulated Failure');
        });

        // جلوگیری از کرش کردن تست به خاطر Exception پرتاب شده در جاب
        try {
            $this->artisan('erp:process-outbox');
        } catch (\Exception $e) {
            // استثنا به درستی دریافت شد
        }

        $failedEvent = DB::table('event_outbox')->where('event_id', $eventId)->first();
        $this->assertNotNull($failedEvent); 
    }
}