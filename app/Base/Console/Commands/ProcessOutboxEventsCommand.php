<?php

namespace App\Base\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Base\Jobs\ProcessOutboxMessageJob;

class ProcessOutboxEventsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'erp:process-outbox {--limit=100 : تعداد رویدادها در هر اجرا}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Poll event_outbox table safely and dispatch jobs to the message broker Queue.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $this->info("Starting Outbox Poller. Fetching up to {$limit} pending events...");

        // استفاده از Transaction و قفل‌گذاری سطرها برای جلوگیری از Race Condition
        DB::transaction(function () use ($limit) {
            $events = DB::table('event_outbox')
                ->where('status', 1) // 1: Pending
                ->orderBy('created_at', 'asc')
                ->limit($limit)
                ->lockForUpdate() // قفل کردن سطرهای خوانده شده تا پایان Transaction
                //->skipLocked()    // عبور از سطرهایی که توسط Worker دیگری قفل شده‌اند
                // حالت اشتباه یا ناشناخته:
                // حالت اصلاح شده و استاندارد پترگورس/لاراول:
                ->lock('FOR UPDATE SKIP LOCKED')
                ->get();

            if ($events->isEmpty()) {
                $this->info('No pending events found.');
                return;
            }

            $eventIds = $events->pluck('event_id')->toArray();

            // تغییر وضعیت به 4 (Queued) تا در Polling بعدی دوباره خوانده نشوند
            DB::table('event_outbox')
                ->whereIn('event_id', $eventIds)
                ->update(['status' => 4]);

            foreach ($events as $eventRecord) {
                // ارسال شناسه رویداد به صف لاراول جهت پردازش ناهمگام
                ProcessOutboxMessageJob::dispatch($eventRecord->event_id);
                $this->line("<info>Dispatched to Queue:</info> Event {$eventRecord->event_id}");
            }
        });

        $this->info('Outbox polling completed successfully.');
        return self::SUCCESS;
    }
}