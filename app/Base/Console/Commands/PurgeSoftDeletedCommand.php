<?php

namespace App\Base\Console\Commands;

use App\Modules\SaasAdmin\Services\SoftDeletePurgeService;
use Illuminate\Console\Command;

/**
 * Physical purge of soft-deleted Org masters past retention.
 * Gated by system_settings retention.purge_job_enabled (unless --force).
 */
class PurgeSoftDeletedCommand extends Command
{
    protected $signature = 'erp:purge-soft-deleted
                            {--force : Run even when retention.purge_job_enabled is false}
                            {--dry-run : Report only (no forceDelete)}';

    protected $description = 'Physically purge soft-deleted Org masters past retention (referential guards).';

    public function handle(SoftDeletePurgeService $purge): int
    {
        if ($this->option('dry-run')) {
            $this->warn('dry-run is reserved; use purge_job_enabled=false to no-op, or inspect logs after a real run.');
        }

        $result = $purge->purgeOrgMasters(force: (bool) $this->option('force'));

        if (! $result['enabled']) {
            $this->warn('Purge skipped: retention.purge_job_enabled is false. Pass --force to override.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Purge complete (cutoff %s): departments=%d branches=%d companies=%d skipped=%d',
            $result['cutoff'],
            $result['departments'],
            $result['branches'],
            $result['companies'],
            count($result['skipped'])
        ));

        if ($result['skipped'] !== [] && $this->output->isVerbose()) {
            foreach (array_slice($result['skipped'], 0, 50) as $line) {
                $this->line('  skip: '.$line);
            }
        }

        return self::SUCCESS;
    }
}
