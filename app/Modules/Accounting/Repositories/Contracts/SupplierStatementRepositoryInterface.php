<?php

namespace App\Modules\Accounting\Repositories\Contracts;

interface SupplierStatementRepositoryInterface
{
    public function getStatement(
        int $supplierId,
        ?string $fromDate = null,
        ?string $toDate = null
    ): array;
}
