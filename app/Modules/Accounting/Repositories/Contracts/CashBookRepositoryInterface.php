<?php

namespace App\Modules\Accounting\Repositories\Contracts;

interface CashBookRepositoryInterface
{
    public function getCashBook(
        ?string $fromDate = null,
        ?string $toDate = null
    ): array;
}