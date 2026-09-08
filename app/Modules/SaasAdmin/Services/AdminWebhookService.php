<?php

namespace App\Modules\SaasAdmin\Services;

use App\Modules\SaasAdmin\Models\AdminWebhook;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class AdminWebhookService
{
    public function list(): Collection
    {
        return AdminWebhook::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function create(string $name, string $targetUrl, array $eventTypes, ?string $secretToken = null): AdminWebhook
    {
        return AdminWebhook::create([
            'webhook_id'   => (string) Str::uuid(),
            'name'         => $name,
            'target_url'   => $targetUrl,
            'secret_token' => $secretToken,
            'event_types'  => $eventTypes,
            'is_active'    => true,
            'created_at'   => now(),
            'row_version'  => 1,
        ]);
    }

    public function update(string $webhookId, ?string $name = null, ?string $targetUrl = null, ?array $eventTypes = null, ?bool $isActive = null): AdminWebhook
    {
        $wh = AdminWebhook::query()->where('webhook_id', $webhookId)->firstOrFail();

        $changes = array_filter([
            'name'        => $name,
            'target_url'  => $targetUrl,
            'event_types' => $eventTypes,
            'is_active'   => $isActive,
        ], fn ($v) => !is_null($v));

        if (!empty($changes)) {
            $changes['updated_at'] = now();
            $changes['row_version'] = ((int) ($wh->row_version ?? 1)) + 1;
            $wh->update($changes);
        }

        return $wh->fresh();
    }

    public function deactivate(string $webhookId): void
    {
        $this->update($webhookId, isActive: false);
    }
}
