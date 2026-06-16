<?php

namespace App\Modules\Sales\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use App\Modules\Sales\Repositories\SaleRepository;
use App\Modules\Sales\Repositories\Contracts\SaleRepositoryInterface;

class SaleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            SaleRepositoryInterface::class,
            SaleRepository::class
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
