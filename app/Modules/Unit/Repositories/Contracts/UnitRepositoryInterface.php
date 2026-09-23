<?php

namespace App\Modules\Unit\Repositories\Contracts;

use App\Modules\Unit\Models\Unit;

use App\Core\Repositories\Contracts\BaseRepositoryInterface;

interface UnitRepositoryInterface
    extends BaseRepositoryInterface
{
    public function findByUuid(
        string $uuid
    ): ?Unit;

    public function findByCode(
        string $code
    ): ?Unit;
}