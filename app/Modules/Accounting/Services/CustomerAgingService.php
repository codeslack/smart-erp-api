<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Repositories\Contracts\CustomerAgingRepositoryInterface;

class CustomerAgingService
{
    public function __construct(
        protected CustomerAgingRepositoryInterface $repository
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
