<?php

namespace App\Base\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * ID-W2-01b — Remind tenant system admins / owners to open a periodic Access Certification campaign.
 *
 * Cadences: 3 months and 6 months (both evaluated).
 * Delivery: event_outbox (identity.access_cert.reminder.v1) for downstream notification channels.
 */
class AccessCertReminderCommand extends Command
{
    protected $signature = 'erp:access-cert-reminders
                            {--dry-run : Report only, do not write outbox or settings}
                            {--tenant= : Limit to one tenant UUID}';

    protected $description = 'Emit Access Certification campaign reminders (3/6 month cadence) to tenant owners';

    /** @var list<int> */
    private const CADENCES = [3, 6];

    /** Minimum days between two reminders for the same tenant (anti-spam). */
    private const MIN_DAYS_BETWEEN_REMINDERS = 25;

    public function handle(): int
    {
        if (!Schema::hasTable('tenant_access_cert_campaigns') || !Schema::hasTable('tenants')) {
            $this->warn('Required tables missing; skip.');

            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');
        $onlyTenant = $this->option('tenant') ? (string) $this->option('tenant') : null;

        $tenantQuery = DB::table('tenants')->select('tenant_id');
        if ($onlyTenant) {
            $tenantQuery->where('tenant_id', $onlyTenant);
        }

        $tenantIds = $tenantQuery->pluck('tenant_id')->map(fn ($id) => (string) $id)->all();
        $emitted = 0;

        foreach ($tenantIds as $tenantId) {
            DB::statement("SELECT set_config('app.current_tenant_id', ?, false)", [$tenantId]);

            if (Schema::hasTable('tenant_access_cert_settings')) {
                $settings = DB::table('tenant_access_cert_settings')->where('tenant_id', $tenantId)->first();
                if ($settings && isset($settings->reminders_enabled) && !(bool) $settings->reminders_enabled) {
                    continue;
                }
            }

            $active = DB::table('tenant_access_cert_campaigns')
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->whereIn('status', ['DRAFT', 'OPEN'])
                ->exists();

            if ($active) {
                continue;
            }

            $lastActivity = DB::table('tenant_access_cert_campaigns')
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->selectRaw('MAX(COALESCE(completed_at, opened_at, created_at)) as last_at')
                ->value('last_at');

            $monthsSince = $lastActivity
                ? (int) floor(now()->diffInDays(\Carbon\Carbon::parse($lastActivity)) / 30)
                : 999;

            $triggeredCadence = null;
            foreach (array_reverse(self::CADENCES) as $cadence) {
                if ($monthsSince >= $cadence) {
                    $triggeredCadence = $cadence;
                    break;
                }
            }

            if ($triggeredCadence === null) {
                continue;
            }

            if (Schema::hasTable('tenant_access_cert_settings')) {
                $settings = DB::table('tenant_access_cert_settings')->where('tenant_id', $tenantId)->first();
                if ($settings && $settings->last_reminder_at) {
                    $daysSinceReminder = now()->diffInDays(\Carbon\Carbon::parse($settings->last_reminder_at));
                    if ($daysSinceReminder < self::MIN_DAYS_BETWEEN_REMINDERS) {
                        continue;
                    }
                }
            }

            $ownerIds = DB::table('tenant_users')
                ->where('tenant_id', $tenantId)
                ->where('status', 1)
                ->whereNull('deleted_at')
                ->where('is_owner', true)
                ->pluck('user_id')
                ->map(fn ($id) => (string) $id)
                ->values()
                ->all();

            if ($ownerIds === []) {
                $this->line("Tenant {$tenantId}: no active owner; skip reminder.");
                continue;
            }

            $payload = [
                'tenant_id' => $tenantId,
                'cadence_months' => $triggeredCadence,
                'months_since_last_campaign' => $monthsSince === 999 ? null : $monthsSince,
                'last_campaign_activity_at' => $lastActivity,
                'recipient_user_ids' => $ownerIds,
                'message_fa' => $triggeredCadence >= 6
                    ? 'بیش از ۶ ماه از آخرین بازبینی دسترسی گذشته است. لطفاً یک کمپین Access Certification جدید ایجاد و باز کنید.'
                    : 'بیش از ۳ ماه از آخرین بازبینی دسترسی گذشته است. لطفاً یک کمپین Access Certification جدید ایجاد و باز کنید.',
                'action_path' => '/dashboard/identity/access-certifications',
            ];

            $this->info("Tenant {$tenantId}: cadence={$triggeredCadence}m owners=".count($ownerIds).($dry ? ' [dry-run]' : ''));

            if ($dry) {
                continue;
            }

            if (Schema::hasTable('event_outbox')) {
                DB::table('event_outbox')->insert([
                    'event_id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'aggregate_type' => 'tenant_access_cert_campaigns',
                    'aggregate_id' => $tenantId,
                    'event_type' => 'identity.access_cert.reminder.v1',
                    'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                    'status' => 1,
                    'retry_count' => 0,
                    'created_at' => now(),
                ]);
            }

            if (Schema::hasTable('tenant_access_cert_settings')) {
                $exists = DB::table('tenant_access_cert_settings')->where('tenant_id', $tenantId)->exists();
                if ($exists) {
                    DB::table('tenant_access_cert_settings')->where('tenant_id', $tenantId)->update([
                        'last_reminder_at' => now(),
                        'last_reminder_cadence_months' => $triggeredCadence,
                        'updated_at' => now(),
                        'row_version' => DB::raw('row_version + 1'),
                    ]);
                } else {
                    DB::table('tenant_access_cert_settings')->insert([
                        'tenant_id' => $tenantId,
                        'preferred_cadence_months' => 3,
                        'reminders_enabled' => true,
                        'last_reminder_at' => now(),
                        'last_reminder_cadence_months' => $triggeredCadence,
                        'created_at' => now(),
                        'updated_at' => now(),
                        'row_version' => 1,
                    ]);
                }
            }

            $emitted++;
        }

        $this->info("Reminders emitted: {$emitted}");

        return self::SUCCESS;
    }
}
