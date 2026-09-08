<?php

namespace App\Base\Console\Commands;

use App\Modules\ProcurementSales\Services\PaymentSettlementService;
use Illuminate\Console\Command;

/** L6-PS-08b – Daily mark of past-due payment schedules as OVERDUE. */
class MarkPaymentSchedulesOverdueCommand extends Command
{
    protected $signature = 'erp:mark-payment-schedules-overdue {--date= : As-of date (Y-m-d), default today}';

    protected $description = 'Mark pending/partial payment schedules past due_date as OVERDUE.';

    public function handle(PaymentSettlementService $settlement): int
    {
        $asOf = $this->option('date') ?: now()->toDateString();
        $count = $settlement->markOverdueSchedules($asOf);
        $this->info("Marked {$count} payment schedule(s) as OVERDUE (as of {$asOf}).");

        return self::SUCCESS;
    }
}
