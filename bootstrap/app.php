<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Base\Http\Middleware\TenantContextMiddleware;
use App\Base\Http\Middleware\RequirePermission;
use App\Base\Http\Middleware\LoadUserScopesMiddleware;
use App\Base\Http\Middleware\RequireScope;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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
        $exceptions->shouldRenderJsonWhen(function ($request, $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(function (\Throwable $e, $request) {
            if (!($request->is('api/*') || $request->expectsJson())) {
                return null;
            }

            // Always log technical details; never expose SQL / stack to clients
            report($e);

            if ($e instanceof ValidationException) {
                return null; // default Laravel validation JSON
            }

            if ($e instanceof ModelNotFoundException) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'مورد درخواستی یافت نشد.',
                ], 404);
            }

            if ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();
                $msg = $e->getMessage();
                if ($msg === '' || str_contains($msg, 'SQLSTATE') || str_contains($msg, 'SQL:')) {
                    $msg = match ($status) {
                        401 => 'احراز هویت لازم است.',
                        403 => 'دسترسی مجاز نیست.',
                        404 => 'مورد درخواستی یافت نشد.',
                        419 => 'نشست منقضی شده است.',
                        429 => 'تعداد درخواست‌ها بیش از حد مجاز است.',
                        default => 'عملیات انجام نشد.',
                    };
                }
                return response()->json([
                    'status'  => 'error',
                    'message' => $msg,
                ], $status);
            }

            if ($e instanceof QueryException) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'خطای داخلی رخ داد. جزئیات در لاگ سیستم ثبت شد.',
                ], 500);
            }

            // Domain / business Exception with safe message
            $msg = $e->getMessage();
            $safe = is_string($msg)
                && $msg !== ''
                && !str_contains($msg, 'SQLSTATE')
                && !str_contains($msg, 'SQL:')
                && !str_contains($msg, 'Stack trace')
                && !str_contains($msg, '/var/www')
                && !str_contains($msg, 'must be of type');

            return response()->json([
                'status'  => 'error',
                'message' => $safe ? $msg : 'عملیات انجام نشد. لطفاً دوباره تلاش کنید.',
            ], $safe ? 422 : 500);
        });
    })->create();
