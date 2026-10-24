<?php

namespace App\Modules\PurchaseOrder\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

use App\Modules\PurchaseOrder\Models\PurchaseOrder;
use App\Modules\PurchaseOrder\Models\PurchaseOrderItem;

use App\Core\Repositories\BaseRepository;
use App\Modules\PurchaseOrder\Repositories\Contracts\PurchaseOrderRepositoryInterface;

class PurchaseOrderRepository
    extends BaseRepository
    implements PurchaseOrderRepositoryInterface
{
    public function __construct(PurchaseOrder $model)
    {
        parent::__construct($model);
    }

    public function findByUuid(string $uuid): ?PurchaseOrder
    {
        return $this->model
            ->newQuery()
            ->where('uuid', $uuid)
            ->first();
    }

    public function findByOrderNumber(string $orderNumber): ?PurchaseOrder
    {
        return $this->model
            ->newQuery()
            ->where('order_number', $orderNumber)
            ->first();
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
            ]);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        if (!empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        if (!empty($filters['order_number'])) {
            $query->where(
                'order_number',
                'like',
                '%' . $filters['order_number'] . '%'
            );
        }

        if (!empty($filters['order_date_from'])) {
            $query->whereDate(
                'order_date',
                '>=',
                $filters['order_date_from']
            );
        }

        if (!empty($filters['order_date_to'])) {
            $query->whereDate(
                'order_date',
                '<=',
                $filters['order_date_to']
            );
        }

        return $query
            ->latest('order_date')
            ->latest('id')
            ->paginate($perPage);
    }

    public function getWithRelations(
        string $uuid,
        array $relations = []
    ): ?PurchaseOrder {
        $defaultRelations = [
            'supplier',
            'warehouse',
            'paymentTerm',
            'items',
            'items.product',
            'items.warehouse',
            'items.unitOfMeasure',
            'items.taxGroup',
        ];

        $relations = $relations ?: $defaultRelations;

        return $this->model
            ->newQuery()
            ->with($relations)
            ->where('uuid', $uuid)
            ->first();
    }

    public function getItems(string $uuid): Collection
    {
        $purchaseOrder = $this->model
            ->newQuery()
            ->where('uuid', $uuid)
            ->first();

        if (!$purchaseOrder) {
            return new Collection();
        }

        return $purchaseOrder
            ->items()
            ->with([
                'product',
                'warehouse',
                'unitOfMeasure',
                'taxGroup',
            ])
            ->get();
    }

    public function findItemForReceiptUpdate(
        int $itemId
    ): ?PurchaseOrderItem {
        return PurchaseOrderItem::query()
            ->where('id', $itemId)
            ->lockForUpdate()
            ->first();
    }
}