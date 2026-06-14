<?php

namespace App\Modules\Customer\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use App\Modules\Customer\Repositories\CustomerRepository;
use App\Modules\Customer\Repositories\Contracts\CustomerRepositoryInterface;

class CustomerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            CustomerRepositoryInterface::class,
            CustomerRepository::class
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
