<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Modules\MasterData\Contracts\ItemLookupContract;
use App\Modules\MasterData\Contracts\WarehouseLookupContract;
use App\Modules\Organization\Contracts\CompanyLookupContract;
use App\Modules\Accounting\Contracts\VoucherPostingContract;

use App\Modules\MasterData\Services\ItemLookupService;
use App\Modules\MasterData\Services\WarehouseLookupService;
use App\Modules\Organization\Services\CompanyLookupService;
use App\Modules\Accounting\Services\VoucherPostingService;

/**
 * Binds Service Contracts (interfaces) to their concrete implementations.
 * Contracts live inside the owner module (per APP folder standard).
 */
class ContractsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ItemLookupContract::class, ItemLookupService::class);
        $this->app->bind(WarehouseLookupContract::class, WarehouseLookupService::class);
        $this->app->bind(CompanyLookupContract::class, CompanyLookupService::class);
        $this->app->bind(VoucherPostingContract::class, VoucherPostingService::class);
    }

    public function boot(): void
    {
        //
    }
}
