<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Contracts\MasterData\ItemLookupContract;
use App\Contracts\MasterData\WarehouseLookupContract;
use App\Contracts\Organization\CompanyLookupContract;

use App\Modules\MasterData\Services\ItemLookupService;
use App\Modules\MasterData\Services\WarehouseLookupService;
use App\Modules\Organization\Services\CompanyLookupService;

/**
 * Binds Service Contracts (interfaces) to their concrete implementations.
 *
 * L6-CROSS-06 – Foundation for inter-module communication without Physical FK.
 */
class ContractsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // MasterData contracts
        $this->app->bind(ItemLookupContract::class, ItemLookupService::class);
        $this->app->bind(WarehouseLookupContract::class, WarehouseLookupService::class);

        // Organization contracts
        $this->app->bind(CompanyLookupContract::class, CompanyLookupService::class);

        // Accounting contracts (VoucherPosting) will be bound later when implementation is ready
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
