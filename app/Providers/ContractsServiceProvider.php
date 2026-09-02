<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Binds Service Contracts (interfaces) to their concrete implementations.
 *
 * L6-CROSS-06 – Foundation for inter-module communication without Physical FK.
 * Concrete implementations will be registered here as modules mature.
 */
class ContractsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // MasterData contracts
        // $this->app->bind(
        //     \App\Contracts\MasterData\ItemLookupContract::class,
        //     \App\Modules\MasterData\Services\ItemLookupService::class
        // );

        // $this->app->bind(
        //     \App\Contracts\MasterData\WarehouseLookupContract::class,
        //     \App\Modules\MasterData\Services\WarehouseLookupService::class
        // );

        // Organization contracts
        // $this->app->bind(
        //     \App\Contracts\Organization\CompanyLookupContract::class,
        //     \App\Modules\Organization\Services\CompanyLookupService::class
        // );

        // Accounting contracts
        // $this->app->bind(
        //     \App\Contracts\Accounting\VoucherPostingContract::class,
        //     \App\Modules\Accounting\Services\VoucherPostingService::class
        // );
    }

    public function boot(): void
    {
        //
    }
}
