<?php

namespace App\Modules\PurchaseOrder\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;

use App\Modules\PurchaseOrder\Models\PurchaseOrderItem;
use App\Core\Repositories\Contracts\BaseRepositoryInterface;

interface PurchaseOrderItemRepositoryInterface
    extends BaseRepositoryInterface
{
    public function createMany(
        int $purchaseOrderId,
        array $items
    ): Collection;

    public function deleteByPurchaseOrder(
        int $purchaseOrderId
    ): int;

    public function findByPurchaseOrder(
        int $purchaseOrderId
    ): Collection;

    /**
     * Lock a purchase order item for
     * receipt-related updates.
     */
    public function findForUpdate(
        int $itemId
    ): ?PurchaseOrderItem;

    public function incrementReceivedQuantity(
        int $itemId,
        string $quantity
    ): int;

    public function decrementReceivedQuantity(
        int $itemId,
        string $quantity
    ): int;

    public function incrementBilledQuantity(
        int $itemId,
        string $quantity
    ): int;

    public function incrementCancelledQuantity(
        int $itemId,
        string $quantity
    ): int;

    public function findPendingReceiptItems(
        int $purchaseOrderId
    ): Collection;

    public function findPendingBillingItems(
        int $purchaseOrderId
    ): Collection;
}