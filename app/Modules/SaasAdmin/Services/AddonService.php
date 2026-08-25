<?php

namespace App\Modules\SaasAdmin\Services;

use App\Modules\SaasAdmin\Models\Addon;
use App\Modules\SaasAdmin\Models\SubscriptionAddon;
use App\Modules\SaasAdmin\Models\Subscription;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AddonService
{
    /**
     * Create a new platform addon.
     */
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

    /**
     * Attach an addon to a subscription with a specific amount.
     */
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

    /**
     * List all active addons.
     */
    public function listActiveAddons()
    {
        return Addon::whereNull('deleted_at')
            ->where('status', 1)
            ->orderBy('code')
            ->get();
    }
}