<?php

namespace App\Modules\StockTransfer\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use App\Modules\StockTransfer\Repositories\StockTransferRepository;
use App\Modules\StockTransfer\Repositories\Contracts\StockTransferRepositoryInterface;

class StockTransferServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            StockTransferRepositoryInterface::class,
            StockTransferRepository::class
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
