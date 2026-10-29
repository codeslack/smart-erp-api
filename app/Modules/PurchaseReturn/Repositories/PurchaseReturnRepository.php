<?php

namespace App\Modules\PurchaseReturn\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Modules\PurchaseReturn\Models\PurchaseReturn;
use App\Modules\PurchaseReturn\Repositories\Contracts\PurchaseReturnRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PurchaseReturnRepository extends BaseRepository implements PurchaseReturnRepositoryInterface
{
    public function __construct(PurchaseReturn $model)
    {
        parent::__construct($model);
    }

    public function paginateWithFilters(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model
            ->newQuery()
            ->with([
                'supplier',
                'purchase',
                'refundAccount',
            ]);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['supplier_uuid'])) {
            $query->whereHas('supplier', fn ($q) => $q->where('uuid', $filters['supplier_uuid']));
        }

        if (! empty($filters['purchase_uuid'])) {
            $query->whereHas('purchase', fn ($q) => $q->where('uuid', $filters['purchase_uuid']));
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

    public function findByUuid(string $uuid): ?PurchaseReturn
    {
        return $this->model
            ->newQuery()
            ->where('uuid', $uuid)
            ->with([
                'supplier',
                'purchase',
                'refundAccount',
                'journalEntry',
                'approvedBy',
                'items.product',
                'items.variant',
                'items.warehouse',
                'items.unit',
                'items.purchaseItem',
                'items.batches',
                'items.serials',
            ])
            ->first();
    }

    public function find(int|string $id): PurchaseReturn
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
                'supplier',
                'purchase',
                'refundAccount',
                'journalEntry',
                'approvedBy',
                'items.product',
                'items.variant',
                'items.warehouse',
                'items.unit',
                'items.purchaseItem',
                'items.batches',
                'items.serials',
            ])
            ->firstOrFail();
    }
}
