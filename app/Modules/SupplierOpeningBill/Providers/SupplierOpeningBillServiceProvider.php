<?php

namespace App\Modules\SupplierOpeningBill\Providers;

use Illuminate\Support\ServiceProvider;

use App\Modules\SupplierOpeningBill\Repositories\SupplierOpeningBillRepository;
use App\Modules\SupplierOpeningBill\Repositories\Contracts\SupplierOpeningBillRepositoryInterface;

class SupplierOpeningBillServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            SupplierOpeningBillRepositoryInterface::class,
            SupplierOpeningBillRepository::class
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
