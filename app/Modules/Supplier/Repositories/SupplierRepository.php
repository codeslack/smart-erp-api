<?php

namespace App\Modules\Supplier\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Modules\Supplier\Models\Supplier;
use App\Modules\Supplier\Repositories\Contracts\SupplierRepositoryInterface;

class SupplierRepository
    extends BaseRepository
    implements SupplierRepositoryInterface
{
    public function __construct(
        Supplier $model
    ) {
        parent::__construct($model);
    }
}
