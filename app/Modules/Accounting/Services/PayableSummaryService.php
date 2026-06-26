<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Repositories\Contracts\PayableSummaryRepositoryInterface;

class PayableSummaryService
{
    public function __construct(
        protected PayableSummaryRepositoryInterface $repository
    ) {}

    public function getReport(): array
    {
        return $this->repository
            ->getReport();
    }
}
