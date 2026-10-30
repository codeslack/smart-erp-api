<?php

namespace App\Modules\SalesQuotation\Repositories\Contracts;

use App\Core\Repositories\Contracts\BaseRepositoryInterface;
use App\Modules\SalesQuotation\Models\SalesQuotation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SalesQuotationRepositoryInterface extends BaseRepositoryInterface
{
    public function paginateWithFilters(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findByUuid(string $uuid): ?SalesQuotation;

    public function findByQuotationNo(string $quotationNo): ?SalesQuotation;

    public function find(int|string $id): SalesQuotation;
}
