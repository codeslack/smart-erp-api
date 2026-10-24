<?php

namespace App\Modules\GoodsReceiptNote\Repositories;

use Illuminate\Database\Eloquent\Collection;

use App\Core\Repositories\BaseRepository;

use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNote;
use App\Modules\GoodsReceiptNote\Repositories\Contracts\GoodsReceiptNoteRepositoryInterface;

class GoodsReceiptNoteRepository
    extends BaseRepository
    implements GoodsReceiptNoteRepositoryInterface
{
    public function __construct(
        GoodsReceiptNote $model
    ) {
        parent::__construct($model);
    }

    public function getByStatus(string $status): Collection
    {
        return $this->model
            ->newQuery()
            ->where('status', $status)
            ->with([
                'supplier',
                'warehouse',
                'purchaseOrder',
            ])
            ->latest('grn_date')
            ->get();
    }

    public function getByPurchaseOrderId(
        int $purchaseOrderId
    ): Collection {
        return $this->model
            ->newQuery()
            ->where('purchase_order_id', $purchaseOrderId)
            ->with([
                'supplier',
                'warehouse',
            ])
            ->latest('grn_date')
            ->get();
    }

    public function getBySupplierId(
        int $supplierId
    ): Collection {
        return $this->model
            ->newQuery()
            ->where('supplier_id', $supplierId)
            ->with([
                'purchaseOrder',
                'warehouse',
            ])
            ->latest('grn_date')
            ->get();
    }

    public function getByWarehouseId(
        int $warehouseId
    ): Collection {
        return $this->model
            ->newQuery()
            ->where('warehouse_id', $warehouseId)
            ->with([
                'supplier',
                'purchaseOrder',
            ])
            ->latest('grn_date')
            ->get();
    }

    public function findByGrnNumber(
        string $grnNumber
    ): ?GoodsReceiptNote {
        return $this->model
            ->newQuery()
            ->where('grn_number', $grnNumber)
            ->first();
    }

    public function findWithItems(
        int $id
    ): ?GoodsReceiptNote {
        return $this->model
            ->newQuery()
            ->with([
                'purchaseOrder',
                'supplier',
                'warehouse',
                'items.product',
                'items.unit',
                'items.purchaseOrderItem',
                'items.batches',
                'items.serials',
            ])
            ->find($id);
    }

    public function findWithItemsByUuid(
        string $uuid
    ): ?GoodsReceiptNote {
        return $this->model
            ->newQuery()
            ->where('uuid', $uuid)
            ->with([
                'purchaseOrder',
                'supplier',
                'warehouse',
                'items.product',
                'items.unit',
                'items.purchaseOrderItem',
                'items.batches',
                'items.serials',
            ])
            ->first();
    }

    public function findWithItemsByUuidForUpdate(
        string $uuid
    ): ?GoodsReceiptNote {
        $grn = $this->model
            ->newQuery()
            ->where('uuid', $uuid)
            ->lockForUpdate()
            ->first();

        if (! $grn) {
            return null;
        }

        return $grn->load([
            'purchaseOrder',
            'supplier',
            'warehouse',
            'items.product',
            'items.unit',
            'items.purchaseOrderItem',
            'items.batches',
            'items.serials',
        ]);
    }
}