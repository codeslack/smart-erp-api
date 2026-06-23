<?php

namespace App\Modules\CustomerReceipt\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use App\Modules\CustomerReceipt\Repositories\CustomerReceiptRepository;
use App\Modules\CustomerReceipt\Repositories\Contracts\CustomerReceiptRepositoryInterface;

class CustomerReceiptServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            CustomerReceiptRepositoryInterface::class,
            CustomerReceiptRepository::class
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
