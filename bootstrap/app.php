<?php

use Illuminate\Http\Request;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

use App\Core\Exceptions\BaseApiException;
use App\Core\Exceptions\ApiExceptionHandler;
use App\Core\Tenant\Middleware\TenantBootstrapMiddleware;

use Spatie\Permission\Middleware\PermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => \App\Core\Tenant\TenantMiddleware::class,
            'tenant.bootstrap' => \App\Core\Tenant\Middleware\TenantBootstrapMiddleware::class,
            'permission.tenant' => \App\Core\Tenant\Middleware\SetPermissionTenant::class,
            'permission' => PermissionMiddleware::class,
        ]);

        $middleware->api(
            prepend: [
                TenantBootstrapMiddleware::class,
            ]
        );

        $middleware->prependToPriorityList(
            \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            \App\Core\Tenant\Middleware\TenantBootstrapMiddleware::class
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn(Request $request) => $request->is('api/*'),
        );

        $exceptions->dontReport([
            BaseApiException::class,
        ]);

        $exceptions->render(
            function (
                \Throwable $exception,
                Request $request
            ) {
                if ($request->is('api/*') || $request->expectsJson()) {
                    return app(
                        ApiExceptionHandler::class
                    )->render($exception);
                }
            }
        );
    })->create();
