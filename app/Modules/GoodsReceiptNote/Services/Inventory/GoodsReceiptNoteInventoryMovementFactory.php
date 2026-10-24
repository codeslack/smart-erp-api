<?php

namespace App\Modules\GoodsReceiptNote\Services\Inventory;

use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNote;
use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNoteItem;

use App\Modules\Inventory\Data\InventoryMovementData;
use App\Modules\Inventory\Enums\InventoryTransactionTypeEnum;

class GoodsReceiptNoteInventoryMovementFactory
{
    public function make(
        GoodsReceiptNote $grn,
        GoodsReceiptNoteItem $item,
        float $quantity,
        float $unitCost,
        ?int $batchId = null,
        ?int $serialId = null,
    ): InventoryMovementData {
        return new InventoryMovementData(
            productId: (int) $item->product_id,
            productVariantId: $item->product_variant_id
                ? (int) $item->product_variant_id
                : null,
            warehouseId: (int) $item->warehouse_id,
            quantity: $quantity,
            unitCost: $unitCost,
            transactionType: InventoryTransactionTypeEnum::PURCHASE->value,
            transactionDate: $grn->grn_date,
            referenceType: GoodsReceiptNote::class,
            referenceId: (int) $grn->getKey(),
            referenceNo: $grn->grn_number,
            batchId: $batchId,
            serialId: $serialId,
            remarks: 'Goods receipt ' . $grn->grn_number,
        );
    }
}