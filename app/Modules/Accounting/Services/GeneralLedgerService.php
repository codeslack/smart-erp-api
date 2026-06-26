<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Repositories\Contracts\GeneralLedgerRepositoryInterface;

class GeneralLedgerService
{
    public function __construct(
        protected GeneralLedgerRepositoryInterface $repository
    ) {}

    public function getLedger(
        int $accountId,
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {

        $account = ChartOfAccount::query()
            ->findOrFail($accountId);

        $transactions = $this->repository
            ->getLedger(
                $accountId,
                $fromDate,
                $toDate
            );

        $openingBalance = 0;

        if ($fromDate) {

            $openingBalance = $account
                ->ledgers()

                ->whereDate(
                    'entry_date',
                    '<',
                    $fromDate
                )

                ->latest('id')

                ->value(
                    'running_balance'
                ) ?? 0;
        }

        $closingBalance = $transactions
            ->last()?->running_balance
            ?? $openingBalance;

        return [

            'account' => [

                'id'
                => $account->id,

                'account_code'
                => $account->account_code,

                'account_name'
                => $account->account_name,

                'account_type'
                => $account->account_type,
            ],

            'opening_balance'
            => $openingBalance,

            'transactions'
            => $transactions,

            'closing_balance'
            => $closingBalance,
        ];
    }
}
