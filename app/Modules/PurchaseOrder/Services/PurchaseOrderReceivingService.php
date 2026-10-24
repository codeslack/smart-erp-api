<?php

namespace App\Modules\PurchaseOrder\Services;

use App\Core\Exceptions\BusinessException;

use App\Modules\ProductUnit\Models\ProductUnit;
use App\Modules\ProductUnit\Services\ProductUnitService;

use App\Modules\PurchaseOrder\Enums\PurchaseOrderStatusEnum;
use App\Modules\PurchaseOrder\Models\PurchaseOrder;
use App\Modules\PurchaseOrder\Models\PurchaseOrderItem;
use App\Modules\PurchaseOrder\Repositories\Contracts\PurchaseOrderItemRepositoryInterface;

class PurchaseOrderReceivingService
{
    public function __construct(
        protected PurchaseOrderItemRepositoryInterface $items,
        protected ProductUnitService $productUnits,
    ) {
    }

    public function receive(
        PurchaseOrder $purchaseOrder,
        PurchaseOrderItem $purchaseOrderItem,
        float $receivedBaseQuantity,
        float $confirmedReceivedBaseQuantity
    ): PurchaseOrderItem {
        $this->ensureReceivable($purchaseOrder);

        if ($receivedBaseQuantity <= 0) {
            throw new BusinessException(
                'Received quantity must be greater than zero.'
            );
        }

        if ($confirmedReceivedBaseQuantity < 0) {
            throw new BusinessException(
                'Confirmed received quantity cannot be negative.'
            );
        }

        $item = $this->lockPurchaseOrderItem(
            $purchaseOrder,
            $purchaseOrderItem->id
        );

        $productUnit = $this->getProductUnit($item);

        $orderedBaseQuantity =
            $this->productUnits->toBaseQuantity(
                $productUnit,
                (float) $item->quantity
            );

        $cancelledQuantity =
            (float) $item->cancelled_quantity;

        $cancelledBaseQuantity = $cancelledQuantity > 0
            ? $this->productUnits->toBaseQuantity(
                $productUnit,
                $cancelledQuantity
            )
            : 0.0;

        $pendingBaseQuantity =
            $orderedBaseQuantity
            - $confirmedReceivedBaseQuantity
            - $cancelledBaseQuantity;

        if ($pendingBaseQuantity < 0) {
            $pendingBaseQuantity = 0;
        }

        if (
            $receivedBaseQuantity >
            $pendingBaseQuantity + 0.0001
        ) {
            throw new BusinessException(
                'Received quantity exceeds pending purchase order quantity.'
            );
        }

        $receivedPoQuantity =
            $this->productUnits->fromBaseQuantity(
                $productUnit,
                $receivedBaseQuantity
            );

        $this->items->incrementReceivedQuantity(
            $item->id,
            (string) $receivedPoQuantity
        );

        return $item->refresh();
    }

    public function lockPurchaseOrderItem(
        PurchaseOrder $purchaseOrder,
        int $purchaseOrderItemId
    ): PurchaseOrderItem {
        $item = $this->items->findForUpdate(
            $purchaseOrderItemId
        );

        if (! $item) {
            throw new BusinessException(
                'Purchase order item not found.'
            );
        }

        if (
            (int) $item->purchase_order_id !==
            (int) $purchaseOrder->id
        ) {
            throw new BusinessException(
                'Purchase order item does not belong to this purchase order.'
            );
        }

        return $item;
    }    

    protected function ensureReceivable(
        PurchaseOrder $purchaseOrder
    ): void {
        if (in_array(
            $purchaseOrder->status,
            [
                PurchaseOrderStatusEnum::DRAFT->value,
                PurchaseOrderStatusEnum::CANCELLED->value,
                PurchaseOrderStatusEnum::CLOSED->value,
                PurchaseOrderStatusEnum::FULLY_RECEIVED->value,
            ],
            true
        )) {
            throw new BusinessException(
                'This purchase order cannot receive goods.'
            );
        }
    }

    protected function getProductUnit(
        PurchaseOrderItem $item
    ): ProductUnit {
        if (
            ! $item->product_id ||
            ! $item->unit_of_measure_id
        ) {
            throw new BusinessException(
                'Product and unit are required for purchase order receiving.'
            );
        }

        $productUnit =
            $this->productUnits->findByProductAndUnit(
                (int) $item->product_id,
                (int) $item->unit_of_measure_id
            );

        if (! $productUnit) {
            throw new BusinessException(
                'The purchase order unit is not configured for this product.'
            );
        }

        $this->productUnits->ensurePurchaseUnit(
            $productUnit
        );

        return $productUnit;
    }
}