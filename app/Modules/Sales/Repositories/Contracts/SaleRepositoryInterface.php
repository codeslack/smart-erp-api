<?php

namespace App\Modules\Sales\Repositories\Contracts;

use App\Core\Repositories\Contracts\BaseRepositoryInterface;
use App\Modules\Sales\Models\Sale;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SaleRepositoryInterface extends BaseRepositoryInterface
{
    public function paginateWithFilters(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findByUuid(string $uuid): ?Sale;

    public function findBySaleNo(string $saleNo): ?Sale;

    public function findByIdForUpdate(int $id): ?Sale;

    public function find(int|string $id): Sale;
}
