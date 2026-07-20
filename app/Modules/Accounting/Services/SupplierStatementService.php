<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Repositories\Contracts\SupplierStatementRepositoryInterface;

class SupplierStatementService
{
    public function __construct(
        protected SupplierStatementRepositoryInterface $repository
    ) {}

    public function statement(
        int $supplierId,
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {

        return $this->repository->getStatement(
            supplierId: $supplierId,
            fromDate: $fromDate,
            toDate: $toDate
        );
    }
}