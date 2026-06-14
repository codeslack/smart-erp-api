<?php

namespace App\Modules\Supplier\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use App\Modules\Supplier\Repositories\SupplierRepository;
use App\Modules\Supplier\Repositories\Contracts\SupplierRepositoryInterface;

class SupplierServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            SupplierRepositoryInterface::class,
            SupplierRepository::class
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
