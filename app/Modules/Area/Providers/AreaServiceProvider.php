<?php

namespace App\Modules\Area\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

use App\Modules\Area\Repositories\AreaRepository;
use App\Modules\Area\Repositories\Contracts\AreaRepositoryInterface;

class AreaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            AreaRepositoryInterface::class,
            AreaRepository::class
        );
    }

    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api')
            ->group(
                __DIR__.'/../Routes/api.php'
            );
    }
}
