<?php

namespace App\Modules\SupplierPayment\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use App\Modules\SupplierPayment\Repositories\SupplierPaymentRepository;
use App\Modules\SupplierPayment\Repositories\Contracts\SupplierPaymentRepositoryInterface;

class SupplierPaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            SupplierPaymentRepositoryInterface::class,
            SupplierPaymentRepository::class
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
