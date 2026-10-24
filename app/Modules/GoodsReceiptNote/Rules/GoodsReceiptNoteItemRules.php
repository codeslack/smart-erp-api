<?php

namespace App\Modules\GoodsReceiptNote\Rules;

use App\Core\Exceptions\BusinessException;
use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNoteItem;

class GoodsReceiptNoteItemRules
{
    public function ensureValidItem(
        GoodsReceiptNoteItem $item
    ): void {
        if (! $item->purchase_order_item_id) {
            throw new BusinessException(
                'Each GRN item must reference a purchase order item.'
            );
        }

        if (! $item->product_id) {
            throw new BusinessException(
                'Each GRN item must reference a product.'
            );
        }

        if (! $item->unit_id) {
            throw new BusinessException(
                'Each GRN item must have a unit.'
            );
        }

        $this->ensurePositiveQuantity(
            (float) $item->quantity_received
        );

        $this->ensurePositiveBaseQuantity(
            (float) $item->base_quantity
        );

        $this->ensureNonNegativeAmount(
            (float) $item->unit_cost,
            'Unit cost'
        );

        $this->ensureNonNegativeAmount(
            (float) $item->line_total,
            'Line total'
        );

        $this->ensureNonNegativeAmount(
            (float) $item->tax_amount,
            'Tax amount'
        );
    }

    public function ensurePositiveQuantity(
        float $quantity
    ): void {
        if ($quantity <= 0) {
            throw new BusinessException(
                'Received quantity must be greater than zero.'
            );
        }
    }

    public function ensurePositiveBaseQuantity(
        float $quantity
    ): void {
        if ($quantity <= 0) {
            throw new BusinessException(
                'Base quantity must be greater than zero.'
            );
        }
    }

    public function ensureNonNegativeAmount(
        float $amount,
        string $field
    ): void {
        if ($amount < 0) {
            throw new BusinessException(
                "{$field} cannot be negative."
            );
        }
    }

    public function ensureBatchQuantity(
        float $receivedQuantity,
        float $batchQuantity
    ): void {
        if (
            bccomp(
                (string) $receivedQuantity,
                (string) $batchQuantity,
                4
            ) !== 0
        ) {
            throw new BusinessException(
                'Total batch quantity must equal the received quantity.'
            );
        }
    }

    public function ensureSerialCount(
        float $receivedQuantity,
        int $serialCount
    ): void {
        if (
            bccomp(
                (string) $receivedQuantity,
                (string) $serialCount,
                4
            ) !== 0
        ) {
            throw new BusinessException(
                'Number of serial numbers must equal the received quantity.'
            );
        }
    }
}