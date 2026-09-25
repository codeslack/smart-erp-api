<?php

namespace App\Modules\Accounting\Repositories\Contracts;

use App\Core\Repositories\Contracts\BaseRepositoryInterface;
use Illuminate\Support\Collection;

interface AccountLedgerRepositoryInterface
    extends BaseRepositoryInterface
{
    public function getBalanceBeforeDate(
        int $tenantId,
        int $accountId,
        string $entryDate
    ): ?string;

    public function getFromDate(
        int $tenantId,
        int $accountId,
        string $entryDate
    ): Collection;
}