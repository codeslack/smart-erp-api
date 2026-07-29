<?php

namespace App\Modules\Warehouse\Repositories;

use App\Modules\Warehouse\Models\Warehouse;

use App\Core\Repositories\BaseRepository;

use App\Modules\Warehouse\Repositories\Contracts\WarehouseRepositoryInterface;

/**
 * @extends BaseRepository<Warehouse>
 */
class WarehouseRepository extends BaseRepository
    implements WarehouseRepositoryInterface
{
    public function __construct(
        Warehouse $model
    ) {
        parent::__construct(
            $model
        );
    }

    public function findByCode(
        string $code
    ): ?Warehouse {

        return $this->model
            ->newQuery()

            ->where(
                'code',
                $code
            )

            ->first();
    }
}