<?php

namespace App\Modules\Tax\Repositories\Contracts;

use App\Core\Repositories\Contracts\BaseRepositoryInterface;

interface TaxGroupComponentRepositoryInterface
    extends BaseRepositoryInterface
{
    public function existsOverlappingPeriod(
        int $taxGroupId,
        int $taxComponentId,
        string $effectiveFrom,
        ?string $effectiveTo,
        ?int $ignoreId = null
    ): bool;
}