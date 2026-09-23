<?php

namespace App\Modules\Tenant\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

use App\Modules\Tenant\Repositories\TenantRepository;
use App\Modules\Tenant\Repositories\Contracts\TenantRepositoryInterface;

class TenantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            TenantRepositoryInterface::class,
            TenantRepository::class
        );
    }

    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api')
            ->group(
                __DIR__ . '/../Routes/api.php'
            );
    }
}