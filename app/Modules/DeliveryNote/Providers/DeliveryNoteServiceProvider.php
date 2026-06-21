<?php

namespace App\Modules\DeliveryNote\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use App\Modules\DeliveryNote\Repositories\DeliveryNoteRepository;
use App\Modules\DeliveryNote\Repositories\Contracts\DeliveryNoteRepositoryInterface;

class DeliveryNoteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            DeliveryNoteRepositoryInterface::class,
            DeliveryNoteRepository::class
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
