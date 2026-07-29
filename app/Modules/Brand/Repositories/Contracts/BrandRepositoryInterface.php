<?php

namespace App\Modules\Brand\Repositories\Contracts;

use App\Modules\Brand\Models\Brand;

use App\Core\Repositories\Contracts\BaseRepositoryInterface;

interface BrandRepositoryInterface
    extends BaseRepositoryInterface
{
    public function findByCode(
        string $code
    ): ?Brand;
}