<?php

namespace App\Modules\Category\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

use App\Modules\Category\Repositories\CategoryRepository;
use App\Modules\Category\Repositories\Contracts\CategoryRepositoryInterface;

class CategoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            CategoryRepositoryInterface::class,
            CategoryRepository::class
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
