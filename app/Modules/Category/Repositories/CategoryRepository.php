<?php

namespace App\Modules\Category\Repositories;

use App\Modules\Category\Models\Category;

use App\Core\Repositories\BaseRepository;

use App\Modules\Category\Repositories\Contracts\CategoryRepositoryInterface;

/**
 * @extends BaseRepository<Category>
 */
class CategoryRepository extends BaseRepository
implements CategoryRepositoryInterface
{
    public function __construct(
        Category $model
    ) {
        parent::__construct(
            $model
        );
    }

    public function findByCode(
        string $code
    ): ?Category {

        return $this->model
            ->newQuery()

            ->where(
                'code',
                $code
            )

            ->first();
    }
}
