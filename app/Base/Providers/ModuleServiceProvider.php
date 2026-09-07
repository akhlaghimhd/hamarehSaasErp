<?php

namespace App\Base\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Event;
use App\Modules\Inventory\Listeners\PurchaseReceiptPostedListener;
use App\Modules\Inventory\Listeners\SalesOrderConfirmedListener;
use App\Modules\ProcurementSales\Events\PurchaseReceiptPostedV1;
use App\Modules\ProcurementSales\Events\SalesOrderConfirmedV1;

class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // معرفی گارد api به هسته لاراول (برای پشتیبانی از تست‌ها و سیستم JWT آینده)
        config(['auth.guards.api' => [
            'driver' => 'session', 
            'provider' => 'users',
        ]]);

        // بایندینگ کانتکست مستأجر
        $this->app->singleton(\App\Base\Context\TenantContext::class, function ($app) {
            return new \App\Base\Context\TenantContext();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->mapApiRoutes();
        $this->loadDynamicMigrations(); // اضافه شدن لودر هوشمند مایگریشن‌ها
        $this->registerCrossModuleEventListeners();

        // ثبت زمان‌بندی (Schedule) پردازش صف Outbox منحصراً در محیط کنسول
        if ($this->app->runningInConsole()) {
            Schedule::command('erp:process-outbox --limit=100')
                ->everyMinute()
                ->withoutOverlapping();
        }
    }

    /**
     * L6-PS-04/05 – Wire boundary events fired by ProcessOutboxMessageJob (string event names).
     */
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
    }

    /**
     * پویش در تمام ماژول‌ها و لود کردن فایل‌های api.php
     */
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

    /**
     * شناسایی و لود کردن تمام پوشه‌های درون database/migrations
     * این کار باعث می‌شود دیتابیس در تست‌ها به درستی ساخته شود
     */
    protected function loadDynamicMigrations(): void
    {
        $mainMigrationPath = database_path('migrations');
        
        if (File::exists($mainMigrationPath)) {
            // پیدا کردن تمام ساب‌فولدرها (مثل master_data, hr, sales)
            $directories = File::directories($mainMigrationPath);
            
            // ترکیب مسیر اصلی و ساب‌فولدرها
            $paths = array_merge([$mainMigrationPath], $directories);
            
            // معرفی مسیرها به هسته لاراول
            $this->loadMigrationsFrom($paths);
        }
    }
}
