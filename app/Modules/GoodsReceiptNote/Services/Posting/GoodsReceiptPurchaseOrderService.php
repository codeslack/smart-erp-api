<?php

namespace App\Modules\GoodsReceiptNote\Services\Posting;

use App\Core\Exceptions\BusinessException;

use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNote;
use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNoteItem;

use App\Modules\PurchaseOrder\Models\PurchaseOrderItem;
use App\Modules\PurchaseOrder\Repositories\Contracts\PurchaseOrderRepositoryInterface;

class GoodsReceiptPurchaseOrderService
{
    public function __construct(
        protected PurchaseOrderRepositoryInterface $purchaseOrders,
    ) {
    }

    /**
     * Validate the GRN against its purchase order.
     *
     * This is non-locking validation.
     * Final quantity validation happens during receive()
     * after the PO item has been locked.
     */
    public function validate(
        GoodsReceiptNote $grn
    ): void {
        $grn->loadMissing([
            'purchaseOrder',
            'items.purchaseOrderItem',
        ]);

        if (! $grn->purchaseOrder) {
            throw new BusinessException(
                'Purchase order not found for this GRN.'
            );
        }

        foreach ($grn->items as $item) {
            $this->validateItem($grn, $item);
        }
    }

    protected function validateItem(
        GoodsReceiptNote $grn,
        GoodsReceiptNoteItem $item
    ): void {
        if (! $item->purchaseOrderItem) {
            throw new BusinessException(
                'Purchase order item not found for GRN item.'
            );
        }

        if (
            (int) $item->purchaseOrderItem->purchase_order_id
            !== (int) $grn->purchase_order_id
        ) {
            throw new BusinessException(
                'GRN item does not belong to the selected purchase order.'
            );
        }
    }

    /**
     * Apply GRN quantities to purchase order items.
     *
     * Must execute inside the caller's DB transaction.
     */
    public function receive(
        GoodsReceiptNote $grn
    ): void {
        $grn->loadMissing('items');

        foreach ($grn->items as $item) {
            $purchaseOrderItem = $this->lockItem($item);

            $this->ensureItemBelongsToOrder(
                $grn,
                $purchaseOrderItem
            );

            $receivedQuantity =
                (float) $item->quantity_received;

            $pendingQuantity =
                $this->pendingQuantity($purchaseOrderItem);

            if ($receivedQuantity > $pendingQuantity) {
                throw new BusinessException(
                    "Received quantity for purchase order item "
                    . "{$purchaseOrderItem->id} cannot exceed "
                    . "pending quantity of {$pendingQuantity}."
                );
            }

            $purchaseOrderItem->received_quantity =
                (float) $purchaseOrderItem->received_quantity
                + $receivedQuantity;

            $purchaseOrderItem->save();
        }
    }

    /**
     * Reverse quantities received by this GRN.
     *
     * Must execute inside the caller's DB transaction.
     */
    public function reverse(
        GoodsReceiptNote $grn
    ): void {
        $grn->loadMissing('items');

        foreach ($grn->items as $item) {
            $purchaseOrderItem = $this->lockItem($item);

            $this->ensureItemBelongsToOrder(
                $grn,
                $purchaseOrderItem
            );

            $receivedQuantity =
                (float) $item->quantity_received;

            $currentReceivedQuantity =
                (float) $purchaseOrderItem->received_quantity;

            if ($receivedQuantity > $currentReceivedQuantity) {
                throw new BusinessException(
                    "Cannot reverse {$receivedQuantity} from "
                    . "purchase order item {$purchaseOrderItem->id}. "
                    . "Current received quantity is "
                    . "{$currentReceivedQuantity}."
                );
            }

            $purchaseOrderItem->received_quantity =
                $currentReceivedQuantity - $receivedQuantity;

            $purchaseOrderItem->save();
        }
    }

    /**
     * Calculate the quantity still available for receipt.
     *
     * Pending = ordered - received - cancelled.
     */
    public function pendingQuantity(
        PurchaseOrderItem $purchaseOrderItem
    ): float {
        $orderedQuantity =
            (float) $purchaseOrderItem->quantity;

        $receivedQuantity =
            (float) $purchaseOrderItem->received_quantity;

        $cancelledQuantity =
            (float) $purchaseOrderItem->cancelled_quantity;

        return max(
            0,
            round(
                $orderedQuantity
                - $receivedQuantity
                - $cancelledQuantity,
                4
            )
        );
    }

    /**
     * Lock the PO item for concurrent GRN processing.
     */
    protected function lockItem(
        GoodsReceiptNoteItem $item
    ): PurchaseOrderItem {
        $purchaseOrderItem =
            $this->purchaseOrders->findItemForReceiptUpdate(
                (int) $item->purchase_order_item_id
            );

        if (! $purchaseOrderItem) {
            throw new BusinessException(
                "Purchase order item "
                . "{$item->purchase_order_item_id} not found."
            );
        }

        return $purchaseOrderItem;
    }

    /**
     * Ensure the PO item belongs to this GRN's PO.
     */
    protected function ensureItemBelongsToOrder(
        GoodsReceiptNote $grn,
        PurchaseOrderItem $purchaseOrderItem
    ): void {
        if (
            (int) $purchaseOrderItem->purchase_order_id
            !== (int) $grn->purchase_order_id
        ) {
            throw new BusinessException(
                "Purchase order item {$purchaseOrderItem->id} "
                . "does not belong to purchase order "
                . "{$grn->purchase_order_id}."
            );
        }
    }
}