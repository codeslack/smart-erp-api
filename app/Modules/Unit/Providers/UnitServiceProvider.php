<?php

namespace App\Modules\Unit\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class UnitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Route::middleware('api')
            ->group(
                __DIR__ . '/../Routes/api.php'
            );
    }
}