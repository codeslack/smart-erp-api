<?php

namespace App\Modules\Accounting\Repositories\Contracts;

use App\Core\Contracts\BaseRepositoryInterface;

interface AccountLedgerRepositoryInterface
    extends BaseRepositoryInterface
{
    public function getLastRunningBalance(
        int $tenantId,
        int $accountId
    ): ?string;
}
