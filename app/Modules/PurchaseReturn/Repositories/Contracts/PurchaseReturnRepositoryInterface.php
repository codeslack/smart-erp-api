<?php

namespace App\Modules\PurchaseReturn\Repositories\Contracts;

use App\Core\Repositories\Contracts\BaseRepositoryInterface;
use App\Modules\PurchaseReturn\Models\PurchaseReturn;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PurchaseReturnRepositoryInterface extends BaseRepositoryInterface
{
    public function paginateWithFilters(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findByUuid(string $uuid): ?PurchaseReturn;

    public function find(int|string $id): PurchaseReturn;
}
