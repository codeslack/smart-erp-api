<?php

namespace App\Modules\GoodsReceiptNote\Services\Posting;

use App\Core\Exceptions\BusinessException;

use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNote;

class GoodsReceiptInventoryPostingService
{
    public function post(
        GoodsReceiptNote $grn
    ): void {
        $grn->load([
            'items.product',
            'items.unit',
            'items.batches',
            'items.serials',
        ]);

        if ($grn->items->isEmpty()) {
            throw new BusinessException(
                'Cannot post inventory for a GRN without items.'
            );
        }

        foreach ($grn->items as $item) {
            $this->postItem($grn, $item);
        }
    }

    protected function postItem(
        GoodsReceiptNote $grn,
        $item
    ): void {
        /*
         * Inventory V4 integration will be implemented here.
         *
         * The GRN module must NOT directly update:
         *
         * - product_stocks
         * - stock_ledgers
         * - product_batches
         * - product_serials
         *
         * Existing Inventory V4 services will own those operations.
         *
         * The integration will pass:
         *
         * - product
         * - warehouse
         * - base quantity
         * - unit cost
         * - tracking type
         * - batch information
         * - serial information
         *
         * to the appropriate Inventory posting service.
         */
    }

    public function reverse(
        GoodsReceiptNote $grn
    ): void {
        /*
         * Reverse the exact inventory movement created by
         * this GRN.
         *
         * The original stock ledger must remain untouched.
         * Reversal should create a separate inventory reversal
         * transaction.
         */
    }
}