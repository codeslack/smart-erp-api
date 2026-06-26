<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Repositories\Contracts\TrialBalanceRepositoryInterface;

class TrialBalanceService
{
    public function __construct(
        protected TrialBalanceRepositoryInterface $repository
    ) {}

    public function getTrialBalance(): array
    {
        $accounts = $this->repository
            ->getTrialBalance();

        $rows = [];

        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($accounts as $account) {

            $balance = (float) $account->current_balance;

            $debit = 0;
            $credit = 0;

            switch ($account->account_type) {

                case 'asset':

                case 'expense':

                    if ($balance >= 0) {

                        $debit = $balance;

                    } else {

                        $credit = abs($balance);
                    }

                    break;

                case 'liability':

                case 'equity':

                case 'income':

                    if ($balance >= 0) {

                        $credit = $balance;

                    } else {

                        $debit = abs($balance);
                    }

                    break;
            }

            $totalDebit += $debit;
            $totalCredit += $credit;

            $rows[] = [

                'account_code'
                    => $account->account_code,

                'account_name'
                    => $account->account_name,

                'account_type'
                    => $account->account_type,

                'debit'
                    => $debit,

                'credit'
                    => $credit,
            ];
        }

        return [

            'accounts'
                => $rows,

            'total_debit'
                => $totalDebit,

            'total_credit'
                => $totalCredit,

            'is_balanced'
                => bccomp(
                    (string) $totalDebit,
                    (string) $totalCredit,
                    4
                ) === 0,
        ];
    }
}
