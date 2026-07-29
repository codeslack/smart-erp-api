<?php

namespace App\Modules\Unit\Repositories;

use App\Modules\Unit\Models\Unit;

use App\Core\Repositories\BaseRepository;

use App\Modules\Unit\Repositories\Contracts\UnitRepositoryInterface;

/**
 * @extends BaseRepository<Unit>
 */
class UnitRepository extends BaseRepository
    implements UnitRepositoryInterface
{
    public function __construct(
        Unit $model
    ) {
        parent::__construct(
            $model
        );
    }

    public function findByCode(
        string $code
    ): ?Unit {

        return $this->model
            ->newQuery()

            ->where(
                'code',
                $code
            )

            ->first();
    }
}