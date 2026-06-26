<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Repositories\Contracts\BalanceSheetRepositoryInterface;

class BalanceSheetService
{
    public function __construct(
        protected BalanceSheetRepositoryInterface $repository
    ) {}

    public function getBalanceSheet(
        ?string $asOfDate = null
    ): array {

        return $this->repository
            ->getBalanceSheet(
                $asOfDate
            );
    }
}