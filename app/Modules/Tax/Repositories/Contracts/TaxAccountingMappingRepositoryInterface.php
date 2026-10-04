<?php

namespace App\Modules\Tax\Repositories\Contracts;

use App\Modules\Tax\Models\TaxAccountingMapping;
use App\Core\Repositories\Contracts\BaseRepositoryInterface;

interface TaxAccountingMappingRepositoryInterface
    extends BaseRepositoryInterface
{
    public function findForPosting(
        int $taxComponentId,
        string $direction,
        string $calculationDate,
    ): ?TaxAccountingMapping;

    public function existsOverlappingPeriod(
        int $taxComponentId,
        string $direction,
        string $effectiveFrom,
        ?string $effectiveTo,
        ?int $ignoreId = null
    ): bool;
}