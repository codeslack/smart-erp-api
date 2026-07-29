<?php

namespace App\Modules\Area\Repositories\Contracts;

use App\Modules\Area\Models\Area;

use App\Core\Repositories\Contracts\BaseRepositoryInterface;

interface AreaRepositoryInterface
    extends BaseRepositoryInterface
{
    public function findByCode(
        string $code
    ): ?Area;
}