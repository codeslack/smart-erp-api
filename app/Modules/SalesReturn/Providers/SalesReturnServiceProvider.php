<?php

namespace App\Modules\SalesReturn\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use App\Modules\SalesReturn\Repositories\SalesReturnRepository;
use App\Modules\SalesReturn\Repositories\Contracts\SalesReturnRepositoryInterface;

class SalesReturnServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            SalesReturnRepositoryInterface::class,
            SalesReturnRepository::class
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
