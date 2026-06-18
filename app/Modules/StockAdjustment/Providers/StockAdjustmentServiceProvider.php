<?php

namespace App\Modules\StockAdjustment\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use App\Modules\StockAdjustment\Repositories\StockAdjustmentRepository;
use App\Modules\StockAdjustment\Repositories\Contracts\StockAdjustmentRepositoryInterface;

class StockAdjustmentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            StockAdjustmentRepositoryInterface::class,
            StockAdjustmentRepository::class
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
