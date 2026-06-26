<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Repositories\Contracts\ReceivableSummaryRepositoryInterface;

class ReceivableSummaryService
{
    public function __construct(
        protected ReceivableSummaryRepositoryInterface $repository
    ) {}

    public function getReport(): array
    {
        return $this->repository
            ->getReport();
    }
}