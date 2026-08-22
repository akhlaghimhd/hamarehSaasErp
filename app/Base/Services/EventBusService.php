<?php

namespace App\Base\Services;

use App\Base\Jobs\ProcessOutboxMessageJob;
use Illuminate\Support\Facades\DB;

class EventBusService
{
    /**
     * خواندن رویدادهای پردازش نشده و ارسال به صف
     */
    public function dispatchPendingEvents(): void
    {
        // خواندن رکوردهای Pending با قفل کردن سطرها برای جلوگیری از اجرای همزمان (Race Condition)
        $pendingEvents = DB::table('event_outbox')
            ->where('status', 1)
            ->orderBy('created_at', 'asc')
            ->limit(100)
            ->lockForUpdate()
            ->get();

        foreach ($pendingEvents as $event) {
            // ارسال به صف (Queue)
            ProcessOutboxMessageJob::dispatch($event)->onQueue('event_bus');
        }
    }
}