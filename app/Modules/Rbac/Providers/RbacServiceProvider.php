<?php

namespace App\Modules\Rbac\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

use App\Modules\Rbac\Repositories\RoleRepository;
use App\Modules\Rbac\Repositories\PermissionRepository;

use App\Modules\Rbac\Repositories\Contracts\RoleRepositoryInterface;
use App\Modules\Rbac\Repositories\Contracts\PermissionRepositoryInterface;

class RbacServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(
            RoleRepositoryInterface::class,
            RoleRepository::class
        );

        $this->app->bind(
            PermissionRepositoryInterface::class,
            PermissionRepository::class
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api')
            ->group(__DIR__ . '/../Routes/api.php');
    }
}
