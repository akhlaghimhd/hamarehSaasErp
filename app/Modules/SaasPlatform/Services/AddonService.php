<?php

namespace App\Modules\SaasPlatform\Services;

use App\Modules\SaasPlatform\Models\Addon;
use App\Modules\SaasPlatform\Models\SubscriptionAddon;
use App\Modules\SaasPlatform\Models\Subscription;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AddonService
{
    public function createAddon(string $code, string $name, ?string $createdBy = null): Addon
    {
        $exists = Addon::where('code', $code)->whereNull('deleted_at')->exists();
        if ($exists) {
            throw new InvalidArgumentException("Addon code [{$code}] already exists.");
        }

        return Addon::create([
            'code'       => $code,
            'name'       => $name,
            'status'     => 1,
            'created_by' => $createdBy,
            'updated_by' => $createdBy,
        ]);
    }

    public function updateAddon(string $addonId, string $name, ?int $status = null, ?string $updatedBy = null): Addon
    {
        $addon = Addon::where('addon_id', $addonId)->whereNull('deleted_at')->firstOrFail();

        $addon->name = $name;
        if ($status !== null) {
            $addon->status = $status;
        }
        $addon->updated_by = $updatedBy;
        $addon->save();

        return $addon->fresh();
    }

    public function softDeleteAddon(string $addonId, ?string $deletedBy = null): bool
    {
        $addon = Addon::where('addon_id', $addonId)->whereNull('deleted_at')->firstOrFail();

        $addon->deleted_by = $deletedBy;
        $addon->save();

        return (bool) $addon->delete();
    }

    public function attachAddonToSubscription(
        string $subscriptionId,
        string $addonId,
        float $amount,
        ?string $createdBy = null
    ): SubscriptionAddon {
        return DB::transaction(function () use ($subscriptionId, $addonId, $amount, $createdBy) {
            Subscription::where('subscription_id', $subscriptionId)->whereNull('deleted_at')->firstOrFail();
            Addon::where('addon_id', $addonId)->whereNull('deleted_at')->firstOrFail();

            return SubscriptionAddon::create([
                'subscription_id' => $subscriptionId,
                'addon_id'        => $addonId,
                'amount'          => $amount,
                'status'          => 1,
                'created_by'      => $createdBy,
                'updated_by'      => $createdBy,
            ]);
        });
    }

    public function listActiveAddons()
    {
        return Addon::whereNull('deleted_at')
            ->where('status', 1)
            ->orderBy('code')
            ->get();
    }
}