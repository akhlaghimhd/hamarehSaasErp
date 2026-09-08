<?php

namespace App\Modules\SaasAdmin\Services;

use App\Modules\SaasAdmin\Models\AdminApiKey;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminApiKeyService
{
    public function listForAdmin(string $adminUserId): Collection
    {
        return AdminApiKey::query()
            ->where('admin_user_id', $adminUserId)
            ->where('is_active', true)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * @return array{model: AdminApiKey, plain_key: string}
     */
    public function create(string $adminUserId, string $name, ?\DateTimeInterface $expiresAt = null): array
    {
        $plain = 'sak_' . Str::random(40);
        $prefix = substr($plain, 0, 10);

        $model = AdminApiKey::create([
            'api_key_id'    => (string) Str::uuid(),
            'admin_user_id' => $adminUserId,
            'name'          => $name,
            'key_prefix'    => $prefix,
            'key_hash'      => hash('sha256', $plain),
            'is_active'     => true,
            'created_at'    => now(),
            'expires_at'    => $expiresAt,
            'row_version'   => 1,
        ]);

        return ['model' => $model, 'plain_key' => $plain];
    }

    public function revoke(string $apiKeyId): void
    {
        $key = AdminApiKey::query()
            ->where('api_key_id', $apiKeyId)
            ->where('is_active', true)
            ->firstOrFail();

        $key->is_active = false;
        $key->row_version = ((int) ($key->row_version ?? 1)) + 1;
        $key->save();
    }
}
