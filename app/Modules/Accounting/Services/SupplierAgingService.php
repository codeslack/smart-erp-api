<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Repositories\Contracts\SupplierAgingRepositoryInterface;

class SupplierAgingService
{
    public function __construct(
        protected SupplierAgingRepositoryInterface $repository
    ) {}

    public function getReport(
        ?string $asOfDate = null
    ): array {

        return $this->repository
            ->getReport(
                $asOfDate
            );
    }
}
