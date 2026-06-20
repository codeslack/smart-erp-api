<?php

namespace App\Modules\GoodsReceiptNote\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use App\Modules\GoodsReceiptNote\Repositories\GoodsReceiptNoteRepository;
use App\Modules\GoodsReceiptNote\Repositories\Contracts\GoodsReceiptNoteRepositoryInterface;

class GoodsReceiptNoteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            GoodsReceiptNoteRepositoryInterface::class,
            GoodsReceiptNoteRepository::class
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
