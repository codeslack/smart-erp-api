<?php

namespace App\Modules\Accounting\Repositories\Contracts;

interface CustomerStatementRepositoryInterface
{
    public function getStatement(
        int $customerId,
        ?string $fromDate = null,
        ?string $toDate = null
    ): array;
}
