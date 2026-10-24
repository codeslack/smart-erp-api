<?php

namespace App\Modules\PurchaseOrder\Services;

use App\Core\Exceptions\BusinessException;
use App\Core\Services\BaseService;

use App\Modules\PurchaseOrder\Models\PurchaseOrderItem;
use App\Modules\PurchaseOrder\Repositories\Contracts\PurchaseOrderItemRepositoryInterface;

class PurchaseOrderItemQuantityService extends BaseService
{
    public function __construct(
        protected PurchaseOrderItemRepositoryInterface $items,
    ) {
    }

    public function addReceivedQuantity(
        PurchaseOrderItem $item,
        float $quantity
    ): void {
        $this->ensurePositiveQuantity($quantity);

        $pendingQuantity =
            (float) $item->quantity
            - (float) $item->received_quantity
            - (float) $item->cancelled_quantity;

        if ($quantity > $pendingQuantity) {
            throw new BusinessException(
                'Received quantity exceeds pending purchase order quantity.'
            );
        }

        $this->items->incrementReceivedQuantity(
            $item->id,
            (string) $quantity
        );
    }

    public function addBilledQuantity(
        PurchaseOrderItem $item,
        float $quantity
    ): void {
        $this->ensurePositiveQuantity($quantity);

        $pendingQuantity =
            (float) $item->received_quantity
            - (float) $item->billed_quantity;

        if ($quantity > $pendingQuantity) {
            throw new BusinessException(
                'Billed quantity exceeds received quantity.'
            );
        }

        $this->items->incrementBilledQuantity(
            $item->id,
            (string) $quantity
        );
    }

    public function addCancelledQuantity(
        PurchaseOrderItem $item,
        float $quantity
    ): void {
        $this->ensurePositiveQuantity($quantity);

        $pendingQuantity =
            (float) $item->quantity
            - (float) $item->received_quantity
            - (float) $item->cancelled_quantity;

        if ($quantity > $pendingQuantity) {
            throw new BusinessException(
                'Cancelled quantity exceeds pending quantity.'
            );
        }

        $this->items->incrementCancelledQuantity(
            $item->id,
            (string) $quantity
        );
    }

    protected function ensurePositiveQuantity(
        float $quantity
    ): void {
        if ($quantity <= 0) {
            throw new BusinessException(
                'Quantity must be greater than zero.'
            );
        }
    }
}