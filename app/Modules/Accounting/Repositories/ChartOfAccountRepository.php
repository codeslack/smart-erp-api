<?php

namespace App\Modules\Accounting\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Modules\Accounting\Models\ChartOfAccount;
use App\Modules\Accounting\Repositories\Contracts\ChartOfAccountRepositoryInterface;

class ChartOfAccountRepository
    extends BaseRepository
    implements ChartOfAccountRepositoryInterface
{
    public function __construct(
        ChartOfAccount $model
    ) {
        parent::__construct(
            $model
        );
    }
}