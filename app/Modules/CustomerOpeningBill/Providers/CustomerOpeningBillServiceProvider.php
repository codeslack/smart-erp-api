<?php

namespace App\Modules\CustomerOpeningBill\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

use App\Modules\CustomerOpeningBill\Repositories\CustomerOpeningBillRepository;
use App\Modules\CustomerOpeningBill\Repositories\Contracts\CustomerOpeningBillRepositoryInterface;

class CustomerOpeningBillServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            CustomerOpeningBillRepositoryInterface::class,
            CustomerOpeningBillRepository::class
        );
    }

    public function boot(): void
    {
        // Route::middleware('api')
        //     ->prefix('api')
        //     ->group(
        //         __DIR__ . '/../Routes/api.php'
        //     );
    }
}
