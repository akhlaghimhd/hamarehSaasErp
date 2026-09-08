<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('erp:process-outbox --limit=100')->everyMinute()->withoutOverlapping();

Schedule::command('erp:mark-payment-schedules-overdue')->dailyAt('01:00')->withoutOverlapping();
