<?php

namespace App\Modules\PaymentTerm\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

use App\Modules\PaymentTerm\Repositories\PaymentTermRepository;
use App\Modules\PaymentTerm\Repositories\Contracts\PaymentTermRepositoryInterface;

class PaymentTermServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            PaymentTermRepositoryInterface::class,
            PaymentTermRepository::class
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