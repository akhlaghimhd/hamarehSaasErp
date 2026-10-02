<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Base\Http\Middleware\TenantContextMiddleware;
use App\Base\Http\Middleware\RequirePermission;
use App\Base\Http\Middleware\LoadUserScopesMiddleware;
use App\Base\Http\Middleware\RequireScope;
use App\Base\Exceptions\DomainException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Build a user-facing Persian validation summary from Laravel error bags.
 *
 * Defined before Application::configure return so the function is always registered
 * when this file is loaded (conditional declarations after return never run).
 *
 * @param  array<string, array<int, string>>  $errors
 */
if (! function_exists('self_persian_validation_message')) {
    function self_persian_validation_message(array $errors): string
    {
        $attrLabels = [
            'code' => 'کد',
            'name' => 'نام',
            'email' => 'ایمیل',
            'password' => 'رمز عبور',
            'mobile' => 'موبایل',
            'first_name' => 'نام',
            'last_name' => 'نام خانوادگی',
            'description' => 'توضیحات',
            'decision' => 'تصمیم',
            'note' => 'یادداشت',
            'status' => 'وضعیت',
            'role_ids' => 'نقش‌ها',
            'user_id' => 'کاربر',
            'permission_ids' => 'مجوزها',
            'parent_role_id' => 'نقش والد',
            'due_at' => 'تاریخ سررسید',
            'scope_type' => 'نوع محدوده',
            'entity_id' => 'موجودیت',
        ];

        $firstKey = array_key_first($errors);
        if ($firstKey === null) {
            return 'لطفاً اطلاعات فرم را بررسی و اصلاح کنید.';
        }

        $firstMsg = $errors[$firstKey][0] ?? '';
        // Already Persian from FormRequest / validate() messages
        if (is_string($firstMsg) && preg_match('/[\x{0600}-\x{06FF}]/u', $firstMsg)) {
            return $firstMsg;
        }

        $label = $attrLabels[$firstKey] ?? 'این فیلد';
        $lower = strtolower((string) $firstMsg);

        if (str_contains($lower, 'required')) {
            return "{$label} الزامی است.";
        }
        if (str_contains($lower, 'must be a string') || str_contains($lower, 'string')) {
            return "{$label} باید متن باشد.";
        }
        if (str_contains($lower, 'must be an integer') || str_contains($lower, 'integer')) {
            return "{$label} باید عدد صحیح باشد.";
        }
        if (str_contains($lower, 'must be a number') || str_contains($lower, 'numeric')) {
            return "{$label} باید عدد باشد.";
        }
        if (str_contains($lower, 'may not be greater') || str_contains($lower, 'max')) {
            return "{$label} بیش از حد مجاز است.";
        }
        if (str_contains($lower, 'must be at least') || str_contains($lower, 'min')) {
            return "{$label} کمتر از حد مجاز است.";
        }
        if (str_contains($lower, 'must be a valid') || str_contains($lower, 'format') || str_contains($lower, 'uuid')) {
            return "{$label} قالب معتبری ندارد.";
        }
        if (str_contains($lower, 'has already been taken') || str_contains($lower, 'unique')) {
            return "{$label} تکراری است.";
        }
        if (str_contains($lower, 'does not exist') || str_contains($lower, 'exists')) {
            return "{$label} در سامانه یافت نشد.";
        }
        if (str_contains($lower, 'must be accepted')) {
            return "{$label} باید تأیید شود.";
        }
        if (str_contains($lower, 'confirmed')) {
            return "تأیید {$label} با مقدار واردشده یکسان نیست.";
        }
        if (str_contains($lower, 'email')) {
            return 'ایمیل معتبر نیست.';
        }
        if (str_contains($lower, 'date')) {
            return "{$label} تاریخ معتبری نیست.";
        }
        if (str_contains($lower, 'in:')) {
            return "مقدار {$label} مجاز نیست.";
        }

        return 'لطفاً اطلاعات فرم را بررسی و اصلاح کنید.';
    }
}

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
                $errors = $e->errors();
                $message = self_persian_validation_message($errors);

                return response()->json([
                    'status'  => 'error',
                    'message' => $message,
                    'errors'  => $errors,
                ], 422);
            }

            // Unauthenticated must be 401 (not swallowed as 422 by the generic handler)
            if ($e instanceof AuthenticationException) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'احراز هویت لازم است.',
                ], 401);
            }

            // Forbidden must be 403
            if ($e instanceof AuthorizationException) {
                $msg = $e->getMessage();
                // Avoid leaking English framework defaults
                if ($msg === '' || preg_match('/^[\x00-\x7F]+$/', $msg)) {
                    $msg = 'دسترسی مجاز نیست.';
                }

                return response()->json([
                    'status'  => 'error',
                    'message' => $msg,
                ], 403);
            }

            if ($e instanceof ModelNotFoundException) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'مورد درخواستی یافت نشد.',
                ], 404);
            }

            if ($e instanceof DomainException) {
                return response()->json([
                    'status'     => 'error',
                    'message'    => $e->getMessage(),
                    'error_code' => $e->errorCode,
                ], 422);
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

            // Prefer Persian; fall back if message is pure English framework text
            if ($safe && preg_match('/[\x{0600}-\x{06FF}]/u', $msg)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => $msg,
                ], 422);
            }

            return response()->json([
                'status'  => 'error',
                'message' => 'عملیات انجام نشد. لطفاً دوباره تلاش کنید.',
            ], 500);
        });
    })->create();
