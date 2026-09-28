<?php

namespace App\Modules\Receipt\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use App\Modules\Receipt\Repositories\ReceiptRepository;
use App\Modules\Receipt\Repositories\Contracts\ReceiptRepositoryInterface;

class ReceiptServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            ReceiptRepositoryInterface::class,
            ReceiptRepository::class
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
