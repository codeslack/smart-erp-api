<?php

namespace App\Modules\SalesQuotation\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use App\Modules\SalesQuotation\Repositories\SalesQuotationRepository;
use App\Modules\SalesQuotation\Repositories\Contracts\SalesQuotationRepositoryInterface;

class SalesQuotationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            SalesQuotationRepositoryInterface::class, SalesQuotationRepository::class
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
