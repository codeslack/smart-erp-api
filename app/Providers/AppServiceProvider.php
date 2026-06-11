<?php

namespace App\Providers;

use App\Core\Tenant\TenantManager;
use App\Core\Tenant\TenantResolver;
use App\Modules\Tenant\Repositories\Contracts\TenantRepositoryInterface;
use App\Modules\Tenant\Repositories\TenantRepository;
use App\Modules\User\Repositories\Contracts\UserRepositoryInterface;
use App\Modules\User\Repositories\UserRepository;
use Illuminate\Support\ServiceProvider;

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
        //
    }
}
