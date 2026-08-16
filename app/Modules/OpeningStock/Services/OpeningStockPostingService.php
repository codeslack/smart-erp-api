<?php

namespace App\Modules\OpeningStock\Services;

use Illuminate\Support\Facades\DB;

use App\Modules\OpeningStock\Models\OpeningStock;

class OpeningStockPostingService
{
    public function post(
        OpeningStock $openingStock
    ): void {

        DB::transaction(
            function () use (
                $openingStock
            ) {

                /*
                |--------------------------------------------------------------------------
                | Validation
                |--------------------------------------------------------------------------
                */

                $openingStock->loadMissing(
                    'items'
                );

                if (
                    $openingStock->items->isEmpty()
                ) {
                    throw new \RuntimeException(
                        'Opening stock has no items.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Future Inventory Posting
                |--------------------------------------------------------------------------
                |
                | 1. Create Product Batches
                | 2. Create Product Serials
                | 3. Update Product Stocks
                | 4. Create Stock Ledgers
                |
                */

                foreach (
                    $openingStock->items
                    as $item
                ) {

                    // TODO:
                    // ProductBatchService

                    // TODO:
                    // ProductSerialService

                    // TODO:
                    // InventoryService::stockIn()

                    // TODO:
                    // StockLedgerService::record()
                }
            }
        );
    }

    public function unpost(
        OpeningStock $openingStock
    ): void {

        DB::transaction(
            function () use (
                $openingStock
            ) {

                /*
                |--------------------------------------------------------------------------
                | Future Reverse Posting
                |--------------------------------------------------------------------------
                |
                | Reverse:
                | Product Stocks
                | Stock Ledgers
                | Batches
                | Serials
                |
                */

                // TODO
            }
        );
    }
}