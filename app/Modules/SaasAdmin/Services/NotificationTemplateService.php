<?php

namespace App\Modules\SaasAdmin\Services;

use App\Modules\SaasAdmin\Models\NotificationTemplate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

class NotificationTemplateService
{
    public function list(): Collection
    {
        return NotificationTemplate::query()
            ->whereNull('deleted_at')
            ->orderBy('template_code')
            ->get();
    }

    public function getByCode(string $code): NotificationTemplate
    {
        return NotificationTemplate::query()
            ->where('template_code', $code)
            ->whereNull('deleted_at')
            ->firstOrFail();
    }

    public function upsert(
        string $templateCode,
        string $title,
        string $bodyTemplate,
        string $channel,
        bool $isActive = true
    ): NotificationTemplate {
        $existing = NotificationTemplate::query()
            ->where('template_code', $templateCode)
            ->whereNull('deleted_at')
            ->first();

        if ($existing) {
            $existing->update([
                'title'         => $title,
                'body_template' => $bodyTemplate,
                'channel'       => $channel,
                'is_active'     => $isActive,
                'updated_at'    => now(),
                'row_version'   => ((int) ($existing->row_version ?? 1)) + 1,
            ]);

            return $existing->fresh();
        }

        return NotificationTemplate::create([
            'template_id'   => (string) Str::uuid(),
            'template_code' => $templateCode,
            'title'         => $title,
            'body_template' => $bodyTemplate,
            'channel'       => $channel,
            'is_active'     => $isActive,
            'created_at'    => now(),
            'row_version'   => 1,
        ]);
    }

    public function softDelete(string $templateId): void
    {
        $t = NotificationTemplate::query()
            ->where('template_id', $templateId)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $t->delete();
    }
}
