<?php

namespace App\Modules\OpeningStock\Services;

use App\Modules\OpeningStock\Models\OpeningStock;

class OpeningStockTotalService
{
    public function recalculate(
        OpeningStock $openingStock
    ): void {
        $openingStock->load([
            'sources.items',
        ]);

        $totalQuantity = 0.0;
        $totalAmount = 0.0;

        foreach ($openingStock->sources as $source) {
            foreach ($source->items as $item) {
                $totalQuantity += (float) $item->quantity;
                $totalAmount += (float) $item->total_cost;
            }
        }

        $openingStock->update([
            'total_quantity' => $totalQuantity,
            'total_amount' => $totalAmount,
        ]);
    }
}