<?php

namespace App\Modules\Brand\Repositories;

use App\Modules\Brand\Models\Brand;

use App\Core\Repositories\BaseRepository;

use App\Modules\Brand\Repositories\Contracts\BrandRepositoryInterface;

/**
 * @extends BaseRepository<Brand>
 */
class BrandRepository extends BaseRepository
implements BrandRepositoryInterface
{
    public function __construct(
        Brand $model
    ) {
        parent::__construct(
            $model
        );
    }

    public function findByCode(
        string $code
    ): ?Brand {

        return $this->model
            ->newQuery()

            ->where(
                'code',
                $code
            )

            ->first();
    }
}
