<?php

namespace App\Modules\Area\Repositories;

use App\Modules\Area\Models\Area;

use App\Core\Repositories\BaseRepository;

use App\Modules\Area\Repositories\Contracts\AreaRepositoryInterface;

/**
 * @extends BaseRepository<Area>
 */
class AreaRepository extends BaseRepository
implements AreaRepositoryInterface
{
    public function __construct(
        Area $model
    ) {
        parent::__construct(
            $model
        );
    }

    public function findByCode(
        string $code
    ): ?Area {

        return $this->model
            ->newQuery()

            ->where(
                'code',
                $code
            )

            ->first();
    }
}
