<?php

namespace App\Modules\Settings\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use App\Modules\Settings\Repositories\SettingRepository;
use App\Modules\Settings\Repositories\Contracts\SettingRepositoryInterface;

class SettingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            SettingRepositoryInterface::class,
            SettingRepository::class
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
