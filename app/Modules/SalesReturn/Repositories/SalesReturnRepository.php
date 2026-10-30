<?php

namespace App\Modules\SalesReturn\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Modules\SalesReturn\Models\SalesReturn;
use App\Modules\SalesReturn\Repositories\Contracts\SalesReturnRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SalesReturnRepository extends BaseRepository implements SalesReturnRepositoryInterface
{
    public function __construct(SalesReturn $model)
    {
        parent::__construct($model);
    }

    public function paginateWithFilters(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model
            ->newQuery()
            ->with([
                'customer',
                'sale',
                'warehouse',
                'refundAccount',
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

        if (! empty($filters['sale_id'])) {
            $query->where('sale_id', $filters['sale_id']);
        }

        if (! empty($filters['sale_uuid'])) {
            $query->whereHas('sale', fn ($q) => $q->where('uuid', $filters['sale_uuid']));
        }

        if (! empty($filters['return_no'])) {
            $query->where('return_no', 'like', '%' . $filters['return_no'] . '%');
        }

        if (! empty($filters['return_date_from'])) {
            $query->whereDate('return_date', '>=', $filters['return_date_from']);
        }

        if (! empty($filters['return_date_to'])) {
            $query->whereDate('return_date', '<=', $filters['return_date_to']);
        }

        return $query
            ->latest('return_date')
            ->latest('id')
            ->paginate($perPage);
    }

    public function findByUuid(string $uuid): ?SalesReturn
    {
        return $this->model
            ->newQuery()
            ->where('uuid', $uuid)
            ->with([
                'customer',
                'sale',
                'warehouse',
                'refundAccount',
                'journalEntry',
                'approvedBy',
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

    public function findByReturnNo(string $returnNo): ?SalesReturn
    {
        return $this->model
            ->newQuery()
            ->where('return_no', $returnNo)
            ->with([
                'customer',
                'sale',
                'warehouse',
                'refundAccount',
                'journalEntry',
                'approvedBy',
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

    public function find(int|string $id): SalesReturn
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
                'sale',
                'warehouse',
                'refundAccount',
                'journalEntry',
                'approvedBy',
                'items.product',
                'items.variant',
                'items.warehouse',
                'items.unit',
                'items.tax',
                'items.batches',
                'items.serials',
            ])
            ->firstOrFail();
    }
}
