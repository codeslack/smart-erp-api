<?php

namespace App\Modules\Accounting\Repositories\Contracts;

interface GeneralLedgerRepositoryInterface
{
    public function getLedger(
        int $accountId,
        ?string $fromDate = null,
        ?string $toDate = null
    );
}
