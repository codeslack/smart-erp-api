<?php

namespace App\Modules\Sales\Repositories;

use App\Modules\Sales\Models\Sale;
use App\Core\Repositories\BaseRepository;
use App\Modules\Sales\Repositories\Contracts\SaleRepositoryInterface;

class SaleRepository extends BaseRepository implements SaleRepositoryInterface
{
    public function __construct(
        Sale $model
    ) {
        parent::__construct(
            $model
        );
    }
}
