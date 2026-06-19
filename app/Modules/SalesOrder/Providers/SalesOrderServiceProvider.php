<?php

namespace App\Modules\SalesOrder\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use App\Modules\SalesOrder\Repositories\SalesOrderRepository;
use App\Modules\SalesOrder\Repositories\Contracts\SalesOrderRepositoryInterface;

class SalesOrderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            SalesOrderRepositoryInterface::class,
            SalesOrderRepository::class
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
