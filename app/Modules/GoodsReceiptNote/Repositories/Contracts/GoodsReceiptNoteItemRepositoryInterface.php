<?php

namespace App\Modules\GoodsReceiptNote\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;

use App\Core\Repositories\Contracts\BaseRepositoryInterface;

use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNoteItem;

interface GoodsReceiptNoteItemRepositoryInterface extends BaseRepositoryInterface
{
    public function getByGoodsReceiptNoteId(
        int $goodsReceiptNoteId
    ): Collection;

    public function getByPurchaseOrderItemId(
        int $purchaseOrderItemId
    ): Collection;

    public function getReceivedQuantityByPurchaseOrderItemId(
        int $purchaseOrderItemId
    ): float;

    public function findByUuid(
        string $uuid
    ): ?GoodsReceiptNoteItem;

    public function findWithTracking(
        int $id
    ): ?GoodsReceiptNoteItem;

    public function findLockedForGoodsReceiptNote(
        int $id,
        int $goodsReceiptNoteId
    ): ?GoodsReceiptNoteItem;
}
