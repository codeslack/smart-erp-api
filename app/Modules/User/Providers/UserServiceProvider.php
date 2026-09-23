<?php

namespace App\Modules\User\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

use App\Modules\User\Repositories\UserRepository;
use App\Modules\User\Repositories\Contracts\UserRepositoryInterface;

class UserServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            UserRepositoryInterface::class,
            UserRepository::class
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