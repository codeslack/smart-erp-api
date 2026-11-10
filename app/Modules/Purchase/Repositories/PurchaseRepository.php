<?php

namespace App\Modules\Purchase\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Repositories\Contracts\PurchaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PurchaseRepository extends BaseRepository implements PurchaseRepositoryInterface
{
    public function __construct(Purchase $model)
    {
        parent::__construct($model);
    }

    public function paginateWithFilters(
        array $filters = [],
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = $this->model
            ->newQuery()
            ->with([
                'supplier',
                'warehouse',
                'purchaseOrder',
                'goodsReceiptNote',
            ]);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['supplier_uuid'])) {
            $query->whereHas('supplier', fn ($q) => $q->where('uuid', $filters['supplier_uuid']));
        } elseif (! empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        if (! empty($filters['warehouse_uuid'])) {
            $query->whereHas('warehouse', fn ($q) => $q->where('uuid', $filters['warehouse_uuid']));
        } elseif (! empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        if (! empty($filters['purchase_no'])) {
            $query->where('purchase_no', 'like', '%' . $filters['purchase_no'] . '%');
        }

        if (! empty($filters['purchase_date_from'])) {
            $query->whereDate('purchase_date', '>=', $filters['purchase_date_from']);
        }

        if (! empty($filters['purchase_date_to'])) {
            $query->whereDate('purchase_date', '<=', $filters['purchase_date_to']);
        }

        return $query
            ->latest('purchase_date')
            ->latest('id')
            ->paginate($perPage);
    }

    public function findByPurchaseNo(string $purchaseNo): ?Purchase
    {
        return $this->model
            ->newQuery()
            ->where('purchase_no', $purchaseNo)
            ->with(['supplier', 'warehouse', 'items.product', 'items.warehouse'])
            ->first();
    }

    public function findByIdForUpdate(int $id): ?Purchase
    {
        return $this->model
            ->newQuery()
            ->whereKey($id)
            ->lockForUpdate()
            ->first();
    }

    public function getBySupplierId(int $supplierId): Collection
    {
        return $this->model
            ->newQuery()
            ->where('supplier_id', $supplierId)
            ->with(['warehouse', 'purchaseOrder'])
            ->latest('purchase_date')
            ->get();
    }

    public function getByGoodsReceiptNoteId(int $goodsReceiptNoteId): ?Purchase
    {
        return $this->model
            ->newQuery()
            ->where('goods_receipt_note_id', $goodsReceiptNoteId)
            ->first();
    }

    public function getByStatus(string $status): Collection
    {
        return $this->model
            ->newQuery()
            ->where('status', $status)
            ->with(['supplier', 'warehouse'])
            ->latest('purchase_date')
            ->get();
    }
}
