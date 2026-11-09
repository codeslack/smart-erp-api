<?php

use Illuminate\Http\Request;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;

use App\Core\Exceptions\BaseApiException;
use App\Core\Exceptions\ApiExceptionHandler;

use App\Core\Tenant\TenantMiddleware;
use App\Core\Tenant\Middleware\TenantBootstrapMiddleware;
use App\Core\Tenant\Middleware\SetPermissionTenant;

use App\Core\Middleware\AutoPermissionMiddleware;

use Spatie\Permission\Middleware\PermissionMiddleware;

return Application::configure(
    basePath: dirname(__DIR__)
)
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        /*
        |--------------------------------------------------------------------------
        | Middleware Aliases
        |--------------------------------------------------------------------------
        */

        $middleware->alias([
            'tenant' => TenantMiddleware::class,
            'tenant.bootstrap' => TenantBootstrapMiddleware::class,
            'permission.tenant' => SetPermissionTenant::class,
            'permission' => PermissionMiddleware::class,
            'permission.auto' => AutoPermissionMiddleware::class,
        ]);

        /*
        |--------------------------------------------------------------------------
        | API Middleware
        |--------------------------------------------------------------------------
        */

        $middleware->api(
            prepend: [
                TenantBootstrapMiddleware::class,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Middleware Priority
        |--------------------------------------------------------------------------
        */

        $middleware->prependToPriorityList(
            AuthenticatesRequests::class,
            TenantBootstrapMiddleware::class
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->dontReport([
            BaseApiException::class,
        ]);

        $exceptions->render(
            function (
                Throwable $exception,
                Request $request
            ) {
                if (
                    $request->is('api/*')
                    || $request->expectsJson()
                ) {
                    return app(
                        ApiExceptionHandler::class
                    )->render($exception);
                }
            }
        );
    })
    ->create();
