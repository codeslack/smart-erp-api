<?php

namespace App\Modules\Tax\Repositories;

use App\Core\Repositories\BaseRepository;

use App\Modules\Tax\Models\Tax;

use App\Modules\Tax\Repositories\Contracts\TaxRepositoryInterface;

class TaxRepository
    extends BaseRepository
    implements TaxRepositoryInterface
{
    public function __construct(
        Tax $model
    ) {
        parent::__construct($model);
    }
}
