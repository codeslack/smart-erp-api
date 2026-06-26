<?php

namespace App\Modules\Accounting\Repositories\Contracts;

interface DayBookRepositoryInterface
{
    public function getDayBook(
        ?string $fromDate = null,
        ?string $toDate = null
    ): array;
}