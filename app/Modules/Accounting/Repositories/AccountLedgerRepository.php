<?php

namespace App\Modules\Accounting\Repositories;

use Illuminate\Support\Collection;

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

    public function getBalanceBeforeDate(
        int $tenantId,
        int $accountId,
        string $entryDate
    ): ?string {
        return $this->model
            ->where('tenant_id', $tenantId)
            ->where('chart_of_account_id', $accountId)
            ->where('entry_date', '<', $entryDate)
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->value('running_balance');
    }

    public function getFromDate(
        int $tenantId,
        int $accountId,
        string $entryDate
    ): Collection {
        return $this->model
            ->where('tenant_id', $tenantId)
            ->where('chart_of_account_id', $accountId)
            ->where('entry_date', '>=', $entryDate)
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();
    }
}