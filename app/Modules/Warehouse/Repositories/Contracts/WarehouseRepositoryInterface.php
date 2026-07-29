<?php

namespace App\Modules\Warehouse\Repositories\Contracts;

use App\Modules\Warehouse\Models\Warehouse;

use App\Core\Repositories\Contracts\BaseRepositoryInterface;

interface WarehouseRepositoryInterface
    extends BaseRepositoryInterface
{
    public function findByCode(
        string $code
    ): ?Warehouse;
}