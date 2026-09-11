<?php

namespace App\Modules\Inventory\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

use App\Modules\Inventory\Repositories\StockLedgerRepository;
use App\Modules\Inventory\Repositories\ProductStockRepository;
use App\Modules\Inventory\Repositories\InventoryCostLayerRepository;
use App\Modules\Inventory\Repositories\InventoryCostLayerConsumptionRepository;

use App\Modules\Inventory\Repositories\Contracts\StockLedgerRepositoryInterface;
use App\Modules\Inventory\Repositories\Contracts\ProductStockRepositoryInterface;
use App\Modules\Inventory\Repositories\Contracts\InventoryCostLayerRepositoryInterface;
use App\Modules\Inventory\Repositories\Contracts\InventoryCostLayerConsumptionRepositoryInterface;

class InventoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            ProductStockRepositoryInterface::class,
            ProductStockRepository::class
        );

        $this->app->bind(
            StockLedgerRepositoryInterface::class,
            StockLedgerRepository::class
        );

        $this->app->bind(
            InventoryCostLayerRepositoryInterface::class,
            InventoryCostLayerRepository::class
        );

        $this->app->bind(
            InventoryCostLayerConsumptionRepositoryInterface::class,
            InventoryCostLayerConsumptionRepository::class
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
