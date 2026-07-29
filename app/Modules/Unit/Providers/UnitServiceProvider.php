<?php

namespace App\Modules\Unit\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

use App\Modules\Unit\Repositories\UnitRepository;
use App\Modules\Unit\Repositories\Contracts\UnitRepositoryInterface;

class UnitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            UnitRepositoryInterface::class,
            UnitRepository::class
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