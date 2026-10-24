<?php

namespace App\Modules\ProductUnit\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

use App\Modules\ProductUnit\Repositories\ProductUnitRepository;
use App\Modules\ProductUnit\Repositories\Contracts\ProductUnitRepositoryInterface;

class ProductUnitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            ProductUnitRepositoryInterface::class,
            ProductUnitRepository::class
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