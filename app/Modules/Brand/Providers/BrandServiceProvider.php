<?php

namespace App\Modules\Brand\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

use App\Modules\Brand\Repositories\BrandRepository;
use App\Modules\Brand\Repositories\Contracts\BrandRepositoryInterface;

class BrandServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            BrandRepositoryInterface::class,
            BrandRepository::class
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
