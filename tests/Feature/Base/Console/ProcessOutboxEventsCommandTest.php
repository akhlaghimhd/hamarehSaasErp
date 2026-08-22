<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

it('processes pending outbox events and sets tenant context', function () {
    // 1. Arrange: تنظیم داده‌های اولیه
    Event::fake();
    
    $tenantId = Str::uuid()->toString();
    $eventId = Str::uuid()->toString();
    
    DB::table('event_outbox')->insert([
        'event_id' => $eventId,
        'tenant_id' => $tenantId,
        'aggregate_type' => 'test_aggregate',
        'aggregate_id' => Str::uuid()->toString(),
        'event_type' => 'test.event.created',
        'payload' => json_encode(['foo' => 'bar']),
        'status' => 1, // 1 = Pending
        'created_at' => now(),
    ]);

    // 2. Act: اجرای کامند از طریق آرتیسان
    $this->artisan('erp:process-outbox')
         ->assertExitCode(0);

    // بررسی خطای ثبت شده در جدول outbox در صورت بروز شکست
    $processedEvent = DB::table('event_outbox')->where('event_id', $eventId)->first();
    
    if ($processedEvent->status == 3) {
        $this->fail("Outbox processing failed with error: " . $processedEvent->error_log);
    }

    // 3. Assert: بررسی موفقیت‌آمیز بودن
    expect($processedEvent->status)->toBe(2) 
        ->and($processedEvent->processed_at)->not->toBeNull();

    // بررسی شلیک رویداد در باس لاراول
    Event::assertDispatched('test.event.created');
});