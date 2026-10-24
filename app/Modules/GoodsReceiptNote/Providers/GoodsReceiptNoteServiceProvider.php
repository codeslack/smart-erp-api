<?php

namespace App\Modules\GoodsReceiptNote\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

use App\Modules\GoodsReceiptNote\Repositories\GoodsReceiptNoteRepository;
use App\Modules\GoodsReceiptNote\Repositories\GoodsReceiptNoteItemRepository;
use App\Modules\GoodsReceiptNote\Repositories\GoodsReceiptNoteItemBatchRepository;
use App\Modules\GoodsReceiptNote\Repositories\GoodsReceiptNoteItemSerialRepository;

use App\Modules\GoodsReceiptNote\Repositories\Contracts\GoodsReceiptNoteRepositoryInterface;
use App\Modules\GoodsReceiptNote\Repositories\Contracts\GoodsReceiptNoteItemRepositoryInterface;
use App\Modules\GoodsReceiptNote\Repositories\Contracts\GoodsReceiptNoteItemBatchRepositoryInterface;
use App\Modules\GoodsReceiptNote\Repositories\Contracts\GoodsReceiptNoteItemSerialRepositoryInterface;

class GoodsReceiptNoteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            GoodsReceiptNoteRepositoryInterface::class,
            GoodsReceiptNoteRepository::class
        );

        $this->app->bind(
            GoodsReceiptNoteItemRepositoryInterface::class,
            GoodsReceiptNoteItemRepository::class
        );

        $this->app->bind(
            GoodsReceiptNoteItemBatchRepositoryInterface::class,
            GoodsReceiptNoteItemBatchRepository::class
        );

        $this->app->bind(
            GoodsReceiptNoteItemSerialRepositoryInterface::class,
            GoodsReceiptNoteItemSerialRepository::class
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
