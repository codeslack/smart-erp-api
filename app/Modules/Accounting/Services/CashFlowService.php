<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Repositories\Contracts\CashFlowRepositoryInterface;

class CashFlowService
{
    public function __construct(
        protected CashFlowRepositoryInterface $repository
    ) {}

    public function getCashFlow(
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {

        return $this->repository
            ->getCashFlow(
                $fromDate,
                $toDate
            );
    }
}
