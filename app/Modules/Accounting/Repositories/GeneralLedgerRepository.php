<?php

namespace App\Modules\Accounting\Repositories;

use App\Modules\Accounting\Models\AccountLedger;
use App\Modules\Accounting\Repositories\Contracts\GeneralLedgerRepositoryInterface;

class GeneralLedgerRepository
    implements GeneralLedgerRepositoryInterface
{
    public function getLedger(
        int $accountId,
        ?string $fromDate = null,
        ?string $toDate = null
    ) {

        return AccountLedger::query()

            ->with([
                'account'
            ])

            ->where(
                'chart_of_account_id',
                $accountId
            )

            ->when(
                $fromDate,
                fn ($q)
                => $q->whereDate(
                    'entry_date',
                    '>=',
                    $fromDate
                )
            )

            ->when(
                $toDate,
                fn ($q)
                => $q->whereDate(
                    'entry_date',
                    '<=',
                    $toDate
                )
            )

            ->orderBy(
                'entry_date'
            )

            ->orderBy(
                'id'
            )

            ->get();
    }
}
