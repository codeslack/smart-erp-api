<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Repositories\Contracts\DayBookRepositoryInterface;

class DayBookService
{
    public function __construct(
        protected DayBookRepositoryInterface $repository
    ) {}

    public function getDayBook(
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {

        return $this->repository
            ->getDayBook(
                $fromDate,
                $toDate
            );
    }
}
