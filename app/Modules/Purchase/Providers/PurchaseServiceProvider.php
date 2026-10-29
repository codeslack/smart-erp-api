<?php

namespace App\Modules\Purchase\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use App\Modules\Purchase\Repositories\PurchaseRepository;
use App\Modules\Purchase\Repositories\Contracts\PurchaseRepositoryInterface;
use App\Modules\Purchase\Repositories\PurchaseItemRepository;
use App\Modules\Purchase\Repositories\Contracts\PurchaseItemRepositoryInterface;

class PurchaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            PurchaseRepositoryInterface::class,
            PurchaseRepository::class
        );

        $this->app->bind(
            PurchaseItemRepositoryInterface::class,
            PurchaseItemRepository::class
        );
    }

    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api')
            ->group(__DIR__ . '/../Routes/api.php');
    }
}
