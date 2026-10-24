<?php

namespace App\Modules\GoodsReceiptNote\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;

use App\Core\Repositories\Contracts\BaseRepositoryInterface;

use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNote;

interface GoodsReceiptNoteRepositoryInterface
    extends BaseRepositoryInterface
{
    public function getByStatus(string $status): Collection;

    public function getByPurchaseOrderId(
        int $purchaseOrderId
    ): Collection;

    public function getBySupplierId(
        int $supplierId
    ): Collection;

    public function getByWarehouseId(
        int $warehouseId
    ): Collection;

    public function findByGrnNumber(
        string $grnNumber
    ): ?GoodsReceiptNote;

    public function findWithItems(
        int $id
    ): ?GoodsReceiptNote;

    public function findWithItemsByUuid(
        string $uuid
    ): ?GoodsReceiptNote;

    public function findWithItemsByUuidForUpdate(
        string $uuid
    ): ?GoodsReceiptNote;
}