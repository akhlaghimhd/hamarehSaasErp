<?php

namespace App\Base\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Event;
use App\Modules\Inventory\Listeners\PurchaseReceiptPostedListener;
use App\Modules\Inventory\Listeners\SalesOrderConfirmedListener;
use App\Modules\Inventory\Listeners\SalesDeliveryPostedListener;
use App\Modules\ProcurementSales\Events\PurchaseReceiptPostedV1;
use App\Modules\ProcurementSales\Events\SalesOrderConfirmedV1;
use App\Modules\ProcurementSales\Events\SalesDeliveryPostedV1;
use App\Modules\ProcurementSales\Listeners\WorkflowTaskCompletedListener;
use App\Modules\Workflow\Events\WorkflowTaskCompletedV1;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        config(['auth.guards.api' => [
            'driver' => 'session',
            'provider' => 'users',
        ]]);

        $this->app->singleton(\App\Base\Context\TenantContext::class, function ($app) {
            return new \App\Base\Context\TenantContext();
        });
    }

    public function boot(): void
    {
        $this->mapApiRoutes();
        $this->loadDynamicMigrations();
        $this->registerCrossModuleEventListeners();

        if ($this->app->runningInConsole()) {
            Schedule::command('erp:process-outbox --limit=100')
                ->everyMinute()
                ->withoutOverlapping();
        }
    }

    protected function registerCrossModuleEventListeners(): void
    {
        Event::listen(
            PurchaseReceiptPostedV1::EVENT_TYPE,
            [PurchaseReceiptPostedListener::class, 'handle']
        );
        Event::listen(
            SalesOrderConfirmedV1::EVENT_TYPE,
            [SalesOrderConfirmedListener::class, 'handle']
        );
        Event::listen(
            SalesDeliveryPostedV1::EVENT_TYPE,
            [SalesDeliveryPostedListener::class, 'handle']
        );
        Event::listen(
            WorkflowTaskCompletedV1::EVENT_TYPE,
            [WorkflowTaskCompletedListener::class, 'handle']
        );
    }

    protected function mapApiRoutes(): void
    {
        $modulesPath = app_path('Modules');

        if (File::exists($modulesPath)) {
            $modules = File::directories($modulesPath);

            foreach ($modules as $module) {
                $moduleName = basename($module);
                $routesPath = $module . '/Routes/api.php';

                if (File::exists($routesPath)) {
                    $prefix = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $moduleName));

                    Route::prefix('api/' . $prefix)
                        ->middleware('api')
                        ->group($routesPath);
                }
            }
        }
    }

    protected function loadDynamicMigrations(): void
    {
        $mainMigrationPath = database_path('migrations');

        if (File::exists($mainMigrationPath)) {
            $directories = File::directories($mainMigrationPath);
            $paths = array_merge([$mainMigrationPath], $directories);
            $this->loadMigrationsFrom($paths);
        }
    }
}
