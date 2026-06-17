<?php

namespace App\Providers;

use Illuminate\Support\Str;
use Dedoc\Scramble\Scramble;
use App\Core\Tenant\TenantManager;
use App\Core\Tenant\TenantResolver;
use Illuminate\Support\ServiceProvider;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use App\Modules\User\Repositories\UserRepository;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use App\Modules\Tenant\Repositories\TenantRepository;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;
use App\Modules\User\Repositories\Contracts\UserRepositoryInterface;
use App\Modules\Tenant\Repositories\Contracts\TenantRepositoryInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            TenantManager::class
        );

        $this->app->singleton(
            TenantResolver::class
        );

        $this->app->bind(
            TenantRepositoryInterface::class,
            TenantRepository::class
        );

        $this->app->bind(
            UserRepositoryInterface::class,
            UserRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureScramble();
    }

    /**
     * Configure Scramble API Documentation Generation.
     */
    protected function configureScramble(): void
    {
        // Shared reference tool to hold document context across closures
        $context = new class {
            public ?OpenApi $openApi = null;
        };

        Scramble::configure()
            ->withDocumentTransformers([
                // Combined Closure 1: Setup Security, App Versions, and Markdown Description
                function (OpenApi $openApi) use ($context) {
                    $context->openApi = $openApi;

                    // Add Bearer Auth Scheme
                    $openApi->components->securitySchemes['bearer'] = SecurityScheme::http('bearer');

                    // Read Package Version
                    $version = config('app.version') ?? '1.0.0';
                    $openApi->info->version = $version . (config('app.debug') ? '-dev' : '');

                    // Load Intro Markdown
                    $introPath = base_path('docs/intro.md');
                    if (file_exists($introPath)) {
                        $openApi->info->description = file_get_contents($introPath);
                    }
                },
            ])
            ->withOperationTransformers(function (Operation $operation, $routeInfo) use ($context) {
                // 1. Automatic Authentication Lock Icons
                $middleware = $routeInfo->route->gatherMiddleware();
                if (collect($middleware)->contains(fn($m) => Str::startsWith($m, 'auth:sanctum') || Str::startsWith($m, 'erp-api'))) {
                    $operation->security[] = new SecurityRequirement(['bearer' => []]);
                }

                // 2. Uniform Structure Response Wrapping

            });
    }
}
