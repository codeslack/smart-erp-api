<?php

namespace App\Modules\Accounting\Repositories;

use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Repositories\Contracts\TrialBalanceRepositoryInterface;

class TrialBalanceRepository
    implements TrialBalanceRepositoryInterface
{
    public function getTrialBalance()
    {
        return ChartOfAccount::query()

            ->where(
                'is_active',
                true
            )

            ->orderBy(
                'account_code'
            )

            ->get();
    }
}
