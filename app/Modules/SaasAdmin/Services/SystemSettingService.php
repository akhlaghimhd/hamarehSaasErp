<?php

namespace App\Modules\SaasAdmin\Services;

use App\Modules\SaasAdmin\Models\SystemSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class SystemSettingService
{
    public function list(): Collection
    {
        return SystemSetting::query()
            ->whereNull('deleted_at')
            ->orderBy('setting_key')
            ->get();
    }

    public function getByKey(string $key): SystemSetting
    {
        return SystemSetting::query()
            ->where('setting_key', $key)
            ->whereNull('deleted_at')
            ->firstOrFail();
    }

    public function get(string $systemSettingId): SystemSetting
    {
        return SystemSetting::query()
            ->where('system_setting_id', $systemSettingId)
            ->whereNull('deleted_at')
            ->firstOrFail();
    }

    public function upsert(
        string $settingKey,
        ?string $settingValue,
        ?string $description = null,
        ?string $actorId = null
    ): SystemSetting {
        return DB::transaction(function () use ($settingKey, $settingValue, $description, $actorId) {
            $setting = SystemSetting::query()
                ->where('setting_key', $settingKey)
                ->whereNull('deleted_at')
                ->first();

            if ($setting) {
                $setting->update([
                    'setting_value' => $settingValue,
                    'description'   => $description ?? $setting->description,
                    'row_version'   => ((int) ($setting->row_version ?? 1)) + 1,
                    'updated_by'    => $actorId,
                ]);

                $this->logEventOutbox(
                    'system_settings',
                    $setting->system_setting_id,
                    'SaasAdmin.SystemSettingUpdated.v1',
                    [
                        'system_setting_id' => $setting->system_setting_id,
                        'setting_key'       => $settingKey,
                        'setting_value'     => $settingValue,
                    ]
                );

                return $setting->fresh();
            }

            $setting = SystemSetting::create([
                'setting_key'   => $settingKey,
                'setting_value' => $settingValue,
                'description'   => $description,
                'created_by'    => $actorId,
                'updated_by'    => $actorId,
            ]);

            $this->logEventOutbox(
                'system_settings',
                $setting->system_setting_id,
                'SaasAdmin.SystemSettingCreated.v1',
                [
                    'system_setting_id' => $setting->system_setting_id,
                    'setting_key'       => $settingKey,
                ]
            );

            return $setting;
        });
    }

    public function softDelete(string $systemSettingId, ?string $deletedBy = null): void
    {
        DB::transaction(function () use ($systemSettingId, $deletedBy) {
            $setting = $this->get($systemSettingId);
            $setting->deleted_by = $deletedBy;
            $setting->save();
            $setting->delete();

            $this->logEventOutbox(
                'system_settings',
                $systemSettingId,
                'SaasAdmin.SystemSettingDeleted.v1',
                ['system_setting_id' => $systemSettingId]
            );
        });
    }

    private function logEventOutbox(
        string $aggregateType,
        string $aggregateId,
        string $eventType,
        array $payload
    ): void {
        DB::table('event_outbox')->insert([
            'event_id'       => Str::uuid()->toString(),
            'tenant_id'      => '00000000-0000-0000-0000-000000000000',
            'aggregate_type' => $aggregateType,
            'aggregate_id'   => $aggregateId,
            'event_type'     => $eventType,
            'payload'        => json_encode($payload),
            'status'         => 1,
            'created_at'     => now(),
        ]);
    }
}
