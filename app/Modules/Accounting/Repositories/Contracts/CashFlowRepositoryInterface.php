<?php

namespace App\Modules\Accounting\Repositories\Contracts;

interface CashFlowRepositoryInterface
{
    public function getCashFlow(
        ?string $fromDate = null,
        ?string $toDate = null
    ): array;
}