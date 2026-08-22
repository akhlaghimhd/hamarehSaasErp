<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Task Scheduler
|--------------------------------------------------------------------------
*/

// صحیح: زمان‌بندی کامندِ پایشگر (Poller) به جای فراخوانی مستقیم Job
Schedule::command('erp:process-outbox --limit=100')->everyMinute()->withoutOverlapping();