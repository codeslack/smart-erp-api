<?php

namespace App\Modules\PurchaseOrder\Repositories;

use Illuminate\Database\Eloquent\Collection;

use App\Modules\PurchaseOrder\Models\PurchaseOrderItem;

use App\Core\Repositories\BaseRepository;
use App\Modules\PurchaseOrder\Repositories\Contracts\PurchaseOrderItemRepositoryInterface;

class PurchaseOrderItemRepository
    extends BaseRepository
    implements PurchaseOrderItemRepositoryInterface
{
    public function __construct(
        PurchaseOrderItem $model
    ) {
        parent::__construct($model);
    }

    public function createMany(
        int $purchaseOrderId,
        array $items
    ): Collection {
        $createdItems = new Collection();

        foreach ($items as $item) {
            $item['purchase_order_id'] = $purchaseOrderId;

            $createdItems->push(
                $this->model
                    ->newQuery()
                    ->create($item)
            );
        }

        return $createdItems;
    }

    public function deleteByPurchaseOrder(
        int $purchaseOrderId
    ): int {
        return $this->model
            ->newQuery()
            ->where('purchase_order_id', $purchaseOrderId)
            ->delete();
    }

    public function findByPurchaseOrder(
        int $purchaseOrderId
    ): Collection {
        return $this->model
            ->newQuery()
            ->where('purchase_order_id', $purchaseOrderId)
            ->get();
    }

    public function findForUpdate(
        int $itemId
    ): ?PurchaseOrderItem {
        return $this->model
            ->newQuery()
            ->whereKey($itemId)
            ->lockForUpdate()
            ->first();
    }

    public function incrementReceivedQuantity(
        int $itemId,
        string $quantity
    ): int {
        return $this->model
            ->newQuery()
            ->whereKey($itemId)
            ->increment(
                'received_quantity',
                $quantity
            );
    }

    public function decrementReceivedQuantity(
        int $itemId,
        string $quantity
    ): int {
        return $this->model
            ->newQuery()
            ->whereKey($itemId)
            ->decrement(
                'received_quantity',
                $quantity
            );
    }

    public function incrementBilledQuantity(
        int $itemId,
        string $quantity
    ): int {
        return $this->model
            ->newQuery()
            ->whereKey($itemId)
            ->increment(
                'billed_quantity',
                $quantity
            );
    }

    public function incrementCancelledQuantity(
        int $itemId,
        string $quantity
    ): int {
        return $this->model
            ->newQuery()
            ->whereKey($itemId)
            ->increment(
                'cancelled_quantity',
                $quantity
            );
    }

    public function findPendingReceiptItems(
        int $purchaseOrderId
    ): Collection {
        return $this->model
            ->newQuery()
            ->where('purchase_order_id', $purchaseOrderId)
            ->whereRaw(
                '(quantity - received_quantity - cancelled_quantity) > 0'
            )
            ->get();
    }

    public function findPendingBillingItems(
        int $purchaseOrderId
    ): Collection {
        return $this->model
            ->newQuery()
            ->where('purchase_order_id', $purchaseOrderId)
            ->whereRaw(
                '(received_quantity - billed_quantity) > 0'
            )
            ->get();
    }
}