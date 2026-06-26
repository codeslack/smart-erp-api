<?php

namespace App\Modules\Accounting\Repositories\Contracts;

interface ProfitLossRepositoryInterface
{
    public function getProfitLoss(
        ?string $fromDate = null,
        ?string $toDate = null
    ): array;
}