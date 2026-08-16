<?php

namespace App\Modules\OpeningStock\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

use App\Modules\OpeningStock\Repositories\OpeningStockRepository;
use App\Modules\OpeningStock\Repositories\Contracts\OpeningStockRepositoryInterface;

class OpeningStockServiceProvider
    extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            OpeningStockRepositoryInterface::class,
            OpeningStockRepository::class
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