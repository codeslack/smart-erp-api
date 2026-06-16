<?php

namespace App\Modules\Purchase\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Repositories\Contracts\PurchaseRepositoryInterface;

class PurchaseRepository
    extends BaseRepository
    implements PurchaseRepositoryInterface
{
    public function __construct(
        Purchase $model
    ) {
        parent::__construct($model);
    }
}