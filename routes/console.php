<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('erp:process-outbox --limit=100')->everyMinute()->withoutOverlapping();

Schedule::command('erp:mark-payment-schedules-overdue')->dailyAt('01:00')->withoutOverlapping();

// ID-W2-01b — periodic Access Certification campaign reminders (3/6 month)
Schedule::command('erp:access-cert-reminders')->dailyAt('08:00')->withoutOverlapping();

// Soft-delete retention purge (Org masters P0) — gated by retention.purge_job_enabled
Schedule::command('erp:purge-soft-deleted')->dailyAt('03:30')->withoutOverlapping();
