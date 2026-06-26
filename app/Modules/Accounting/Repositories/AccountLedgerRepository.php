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
}
