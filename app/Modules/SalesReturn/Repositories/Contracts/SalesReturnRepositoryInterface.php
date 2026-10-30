<?php

namespace App\Modules\SalesReturn\Repositories\Contracts;

use App\Core\Repositories\Contracts\BaseRepositoryInterface;
use App\Modules\SalesReturn\Models\SalesReturn;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SalesReturnRepositoryInterface extends BaseRepositoryInterface
{
    public function paginateWithFilters(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findByUuid(string $uuid): ?SalesReturn;

    public function findByReturnNo(string $returnNo): ?SalesReturn;

    public function find(int|string $id): SalesReturn;
}
