<?php

namespace App\Modules\SalesOrder\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Modules\SalesOrder\Models\SalesOrder;
use App\Modules\SalesOrder\Repositories\Contracts\SalesOrderRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SalesOrderRepository extends BaseRepository implements SalesOrderRepositoryInterface
{
    public function __construct(SalesOrder $model)
    {
        parent::__construct($model);
    }

    public function paginateWithFilters(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model
            ->newQuery()
            ->with([
                'customer',
                'warehouse',
                'paymentTerm',
            ]);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (! empty($filters['customer_uuid'])) {
            $query->whereHas('customer', fn ($q) => $q->where('uuid', $filters['customer_uuid']));
        }

        if (! empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        if (! empty($filters['warehouse_uuid'])) {
            $query->whereHas('warehouse', fn ($q) => $q->where('uuid', $filters['warehouse_uuid']));
        }

        if (! empty($filters['order_number'])) {
            $query->where('order_number', 'like', '%' . $filters['order_number'] . '%');
        }

        if (! empty($filters['order_date_from'])) {
            $query->whereDate('order_date', '>=', $filters['order_date_from']);
        }

        if (! empty($filters['order_date_to'])) {
            $query->whereDate('order_date', '<=', $filters['order_date_to']);
        }

        return $query
            ->latest('order_date')
            ->latest('id')
            ->paginate($perPage);
    }

    public function findByUuid(string $uuid): ?SalesOrder
    {
        return $this->model
            ->newQuery()
            ->where('uuid', $uuid)
            ->with([
                'customer',
                'warehouse',
                'paymentTerm',
                'salesQuotation',
                'items.product',
                'items.productVariant',
                'items.warehouse',
                'items.unit',
                'items.taxGroup',
            ])
            ->first();
    }

    public function findByOrderNumber(string $orderNumber): ?SalesOrder
    {
        return $this->model
            ->newQuery()
            ->where('order_number', $orderNumber)
            ->with([
                'customer',
                'warehouse',
                'paymentTerm',
                'salesQuotation',
                'items.product',
                'items.productVariant',
                'items.warehouse',
                'items.unit',
                'items.taxGroup',
            ])
            ->first();
    }

    public function find(int|string $id): SalesOrder
    {
        return $this->model
            ->newQuery()
            ->where(function ($q) use ($id) {
                if (is_numeric($id)) {
                    $q->where('id', (int) $id);
                }
                $q->orWhere('uuid', (string) $id);
            })
            ->with([
                'customer',
                'warehouse',
                'paymentTerm',
                'salesQuotation',
                'items.product',
                'items.productVariant',
                'items.warehouse',
                'items.unit',
                'items.taxGroup',
            ])
            ->firstOrFail();
    }
}
