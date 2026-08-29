<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Base\Http\Middleware\TenantContextMiddleware;
use App\Base\Http\Middleware\RequirePermission;
use App\Base\Http\Middleware\LoadUserScopesMiddleware;
use App\Base\Http\Middleware\RequireScope;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        // Shared security middleware aliases (Identity / Isolation)
        $middleware->alias([
            'tenant.context' => TenantContextMiddleware::class,
            'permission'     => RequirePermission::class,
            'load.scopes'    => LoadUserScopesMiddleware::class,
            'scope'          => RequireScope::class, // F3 — Validate Scope on actions
        ]);

    })
    ->withCommands([
        __DIR__.'/../app/Base/Console/Commands',
    ])
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
