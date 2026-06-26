<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Repositories\Contracts\ProfitLossRepositoryInterface;

class ProfitLossService
{
    public function __construct(
        protected ProfitLossRepositoryInterface $repository
    ) {}

    public function getProfitLoss(
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {
        
        return $this->repository
            ->getProfitLoss(
                $fromDate,
                $toDate
            );
    }
}
