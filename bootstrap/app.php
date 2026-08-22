<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Base\Http\Middleware\TenantContextMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        
        // ثبت میدل‌ور مدیریت کانتکست شرکت با نام مستعار
        $middleware->alias([
            'tenant.context' => TenantContextMiddleware::class,
        ]);

    })
    ->withCommands([
        __DIR__.'/../app/Base/Console/Commands', // 🔴 اضافه شدن مسیر کامندهای Base
    ])
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();