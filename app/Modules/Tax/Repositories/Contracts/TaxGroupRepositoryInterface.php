<?php

namespace App\Modules\Tax\Repositories\Contracts;

use App\Modules\Tax\Models\TaxGroup;
use App\Core\Repositories\Contracts\BaseRepositoryInterface;

interface TaxGroupRepositoryInterface extends BaseRepositoryInterface
{
    public function findForCalculation(
        int $taxGroupId,
        string $calculationDate,
    ): ?TaxGroup;
}