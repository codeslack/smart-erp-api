<?php

namespace App\Modules\Warehouse\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

use App\Modules\Warehouse\Repositories\WarehouseRepository;
use App\Modules\Warehouse\Repositories\Contracts\WarehouseRepositoryInterface;

class WarehouseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            WarehouseRepositoryInterface::class,
            WarehouseRepository::class
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