<?php

namespace App\Modules\SalesQuotation\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Modules\SalesQuotation\Models\SalesQuotation;
use App\Modules\SalesQuotation\Repositories\Contracts\SalesQuotationRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SalesQuotationRepository extends BaseRepository implements SalesQuotationRepositoryInterface
{
    public function __construct(SalesQuotation $model)
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
                'convertedSalesOrder',
                'convertedSale',
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

        if (! empty($filters['quotation_no'])) {
            $query->where('quotation_no', 'like', '%' . $filters['quotation_no'] . '%');
        }

        if (! empty($filters['quotation_date_from'])) {
            $query->whereDate('quotation_date', '>=', $filters['quotation_date_from']);
        }

        if (! empty($filters['quotation_date_to'])) {
            $query->whereDate('quotation_date', '<=', $filters['quotation_date_to']);
        }

        return $query
            ->latest('quotation_date')
            ->latest('id')
            ->paginate($perPage);
    }

    public function findByUuid(string $uuid): ?SalesQuotation
    {
        return $this->model
            ->newQuery()
            ->where('uuid', $uuid)
            ->with([
                'customer',
                'warehouse',
                'convertedSalesOrder',
                'convertedSale',
                'creator',
                'items.product',
                'items.variant',
                'items.warehouse',
                'items.unit',
                'items.tax',
            ])
            ->first();
    }

    public function findByQuotationNo(string $quotationNo): ?SalesQuotation
    {
        return $this->model
            ->newQuery()
            ->where('quotation_no', $quotationNo)
            ->with(['customer', 'warehouse', 'items'])
            ->first();
    }

    public function find(int|string $id): SalesQuotation
    {
        if (is_string($id) && ! is_numeric($id)) {
            $record = $this->findByUuid($id);
            if ($record) {
                return $record;
            }
        }

        /** @var SalesQuotation */
        return $this->model
            ->newQuery()
            ->with([
                'customer',
                'warehouse',
                'convertedSalesOrder',
                'convertedSale',
                'creator',
                'items.product',
                'items.variant',
                'items.warehouse',
                'items.unit',
                'items.tax',
            ])
            ->findOrFail($id);
    }
}
