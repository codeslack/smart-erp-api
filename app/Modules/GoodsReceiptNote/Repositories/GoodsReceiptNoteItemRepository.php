<?php

namespace App\Modules\GoodsReceiptNote\Repositories;

use Illuminate\Database\Eloquent\Collection;

use App\Core\Repositories\BaseRepository;

use App\Modules\GoodsReceiptNote\Enums\GoodsReceiptNoteStatusEnum;

use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNoteItem;
use App\Modules\GoodsReceiptNote\Repositories\Contracts\GoodsReceiptNoteItemRepositoryInterface;

class GoodsReceiptNoteItemRepository
extends BaseRepository
implements GoodsReceiptNoteItemRepositoryInterface
{
    public function __construct(
        GoodsReceiptNoteItem $model
    ) {
        parent::__construct($model);
    }

    public function getByGoodsReceiptNoteId(
        int $goodsReceiptNoteId
    ): Collection {
        return $this->model
            ->newQuery()
            ->where(
                'goods_receipt_note_id',
                $goodsReceiptNoteId
            )
            ->with([
                'product',
                'unit',
                'purchaseOrderItem',
                'batches',
                'serials',
            ])
            ->orderBy('id')
            ->get();
    }

    public function getByPurchaseOrderItemId(
        int $purchaseOrderItemId
    ): Collection {
        return $this->model
            ->newQuery()
            ->where(
                'purchase_order_item_id',
                $purchaseOrderItemId
            )
            ->with([
                'goodsReceiptNote',
                'product',
                'unit',
            ])
            ->orderBy('id')
            ->get();
    }

    public function getReceivedQuantityByPurchaseOrderItemId(
        int $purchaseOrderItemId
    ): float {
        return (float) $this->model
            ->newQuery()
            ->where(
                'purchase_order_item_id',
                $purchaseOrderItemId
            )
            ->whereHas(
                'goodsReceiptNote',
                fn($query) => $query->where(
                    'status',
                    GoodsReceiptNoteStatusEnum::CONFIRMED->value
                )
            )
            ->sum('base_quantity');
    }

    public function findByUuid(
        string $uuid
    ): ?GoodsReceiptNoteItem {
        return $this->model
            ->newQuery()
            ->with([
                'goodsReceiptNote',
                'product',
                'productVariant',
                'warehouse',
                'unit',
                'taxGroup',
                'purchaseOrderItem',
                'batches',
                'serials',
            ])
            ->where('uuid', $uuid)
            ->first();
    }

    public function findWithTracking(
        int $id
    ): ?GoodsReceiptNoteItem {
        return $this->model
            ->newQuery()
            ->with([
                'goodsReceiptNote',
                'product',
                'productVariant',
                'warehouse',
                'unit',
                'taxGroup',
                'purchaseOrderItem',
                'batches',
                'serials',
            ])
            ->find($id);
    }

    public function findLockedForGoodsReceiptNote(
        int $id,
        int $goodsReceiptNoteId
    ): ?GoodsReceiptNoteItem {
        return $this->model
            ->newQuery()
            ->whereKey($id)
            ->where(
                'goods_receipt_note_id',
                $goodsReceiptNoteId
            )
            ->lockForUpdate()
            ->first();
    }
}
