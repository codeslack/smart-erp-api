<?php

namespace App\Modules\Accounting\Repositories\Contracts;

interface BalanceSheetRepositoryInterface
{
    public function getBalanceSheet(
        ?string $asOfDate = null
    ): array;
}