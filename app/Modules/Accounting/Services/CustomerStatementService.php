<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Repositories\Contracts\CustomerStatementRepositoryInterface;

class CustomerStatementService
{
    public function __construct(
        protected CustomerStatementRepositoryInterface $repository
    ) {}

    public function getStatement(
        int $customerId,
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {

        return $this->repository
            ->getStatement(
                $customerId,
                $fromDate,
                $toDate
            );
    }
}