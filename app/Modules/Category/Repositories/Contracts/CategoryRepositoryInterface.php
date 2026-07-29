<?php

namespace App\Modules\Category\Repositories\Contracts;

use App\Modules\Category\Models\Category;

use App\Core\Repositories\Contracts\BaseRepositoryInterface;

interface CategoryRepositoryInterface
    extends BaseRepositoryInterface
{
    public function findByCode(
        string $code
    ): ?Category;
}