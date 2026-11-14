<?php

namespace App\Modules\GoodsReceiptNote\Services\Inventory;

use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNote;
use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNoteItem;
use App\Modules\Purchase\Models\Purchase;

use App\Modules\Inventory\Data\InventoryMovementData;
use App\Modules\Inventory\Enums\InventoryTransactionTypeEnum;

class GoodsReceiptNoteInventoryMovementFactory
{
    public function make(
        Purchase $purchase,
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
            transactionDate: $purchase->purchase_date,
            referenceType: Purchase::class,
            referenceId: (int) $purchase->getKey(),
            referenceNo: $purchase->purchase_no,
            batchId: $batchId,
            serialId: $serialId,
            remarks: 'Purchase ' . $purchase->purchase_no
                . ' received through GRN ' . $grn->grn_number,
        );
    }
}
