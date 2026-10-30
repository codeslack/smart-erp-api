<?php

namespace App\Modules\SalesOrder\Repositories\Contracts;

use App\Core\Repositories\Contracts\BaseRepositoryInterface;
use App\Modules\SalesOrder\Models\SalesOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SalesOrderRepositoryInterface extends BaseRepositoryInterface
{
    public function paginateWithFilters(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findByUuid(string $uuid): ?SalesOrder;

    public function findByOrderNumber(string $orderNumber): ?SalesOrder;

    public function find(int|string $id): SalesOrder;
}
