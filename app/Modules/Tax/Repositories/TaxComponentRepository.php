<?php

namespace App\Modules\Tax\Repositories;

use App\Core\Repositories\BaseRepository;

use App\Modules\Tax\Models\TaxComponent;

use App\Modules\Tax\Repositories\Contracts\TaxComponentRepositoryInterface;

class TaxComponentRepository
    extends BaseRepository
    implements TaxComponentRepositoryInterface
{
    public function __construct(
        TaxComponent $model
    ) {
        parent::__construct($model);
    }
}
