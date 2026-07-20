<?php

namespace App\Modules\Accounting\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Modules\Accounting\Models\AccountLedger;
use App\Modules\Accounting\Repositories\Contracts\AccountLedgerRepositoryInterface;

class AccountLedgerRepository
    extends BaseRepository
    implements AccountLedgerRepositoryInterface
{
    public function __construct(
        AccountLedger $model
    ) {
        parent::__construct(
            $model
        );
    }

    public function getLastRunningBalance(
        int $tenantId,
        int $accountId
    ): ?string {

        return $this->model
            ->where(
                'tenant_id',
                $tenantId
            )
            ->where(
                'chart_of_account_id',
                $accountId
            )
            ->latest('id')
            ->value(
                'running_balance'
            );
    }    
}
