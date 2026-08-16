<?php

namespace App\Modules\Product\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

use App\Modules\Product\Repositories\ProductRepository;
use App\Modules\Product\Repositories\Contracts\ProductRepositoryInterface;

use App\Modules\Product\Repositories\ProductVariantRepository;
use App\Modules\Product\Repositories\Contracts\ProductVariantRepositoryInterface;

class ProductServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            ProductRepositoryInterface::class,
            ProductRepository::class
        );

        $this->app->bind(
            ProductVariantRepositoryInterface::class,
            ProductVariantRepository::class
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
