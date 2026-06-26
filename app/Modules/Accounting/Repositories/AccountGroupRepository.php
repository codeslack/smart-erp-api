<?php

namespace App\Modules\Accounting\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Modules\Accounting\Models\AccountGroup;
use App\Modules\Accounting\Repositories\Contracts\AccountGroupRepositoryInterface;

class AccountGroupRepository
    extends BaseRepository
    implements AccountGroupRepositoryInterface
{
    public function __construct(
        AccountGroup $model
    ) {
        parent::__construct(
            $model
        );
    }
}