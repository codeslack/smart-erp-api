<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Repositories\Contracts\CashBookRepositoryInterface;

class CashBookService
{
    public function __construct(
        protected CashBookRepositoryInterface $repository
    ) {}

    public function getCashBook(
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {

        return $this->repository
            ->getCashBook(
                $fromDate,
                $toDate
            );
    }
}