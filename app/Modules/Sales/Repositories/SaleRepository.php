<?php

namespace App\Modules\Sales\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Repositories\Contracts\SaleRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SaleRepository extends BaseRepository implements SaleRepositoryInterface
{
    public function __construct(Sale $model)
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

        if (! empty($filters['sale_no'])) {
            $query->where('sale_no', 'like', '%' . $filters['sale_no'] . '%');
        }

        if (! empty($filters['sale_date_from'])) {
            $query->whereDate('sale_date', '>=', $filters['sale_date_from']);
        }

        if (! empty($filters['sale_date_to'])) {
            $query->whereDate('sale_date', '<=', $filters['sale_date_to']);
        }

        return $query
            ->latest('sale_date')
            ->latest('id')
            ->paginate($perPage);
    }

    public function findByUuid(string $uuid): ?Sale
    {
        return $this->model
            ->newQuery()
            ->where('uuid', $uuid)
            ->with([
                'customer',
                'warehouse',
                'paymentTerm',
                'salesOrder',
                'journalEntry',
                'items.product',
                'items.variant',
                'items.warehouse',
                'items.unit',
                'items.tax',
                'items.batches',
                'items.serials',
                'advanceAllocations.source',
            ])
            ->first();
    }

    public function findBySaleNo(string $saleNo): ?Sale
    {
        return $this->model
            ->newQuery()
            ->where('sale_no', $saleNo)
            ->with([
                'customer',
                'warehouse',
                'paymentTerm',
                'salesOrder',
                'journalEntry',
                'items.product',
                'items.variant',
                'items.warehouse',
                'items.unit',
                'items.tax',
                'items.batches',
                'items.serials',
            ])
            ->first();
    }

    public function find(int|string $id): Sale
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
                'salesOrder',
                'journalEntry',
                'items.product',
                'items.variant',
                'items.warehouse',
                'items.unit',
                'items.tax',
                'items.batches',
                'items.serials',
                'advanceAllocations.source',
            ])
            ->firstOrFail();
    }
}
