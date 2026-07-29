<?php

namespace App\Modules\Category\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

use App\Modules\Category\Models\Category;

use App\Modules\SystemNumber\Enums\SystemNumberTypeEnum;

use App\Modules\Category\Repositories\Contracts\CategoryRepositoryInterface;

class CategoryService
{
    public function __construct(
        protected CategoryRepositoryInterface $repository
    ) {}

    public function paginate(
        int $perPage = 15
    ): LengthAwarePaginator {

        return $this->repository
            ->paginate($perPage);
    }

    public function create(
        array $data
    ): Category {

        return DB::transaction(
            function () use ($data) {

                $data['code'] ??=
                    nextSystemNumber(
                        SystemNumberTypeEnum::CATEGORY
                    );

                return $this->repository
                    ->create($data);
            }
        );
    }

    public function update(
        Category $category,
        array $data
    ): Category {

        return DB::transaction(
            function () use (
                $category,
                $data
            ) {

                return $this->repository
                    ->update(
                        $category,
                        $data
                    );
            }
        );
    }

    public function delete(
        Category $category
    ): bool {

        return DB::transaction(
            fn () => $this->repository
                ->delete(
                    $category
                )
        );
    }

    public function findById(
        int $id
    ): ?Category {

        return $this->repository
            ->findById($id);
    }

    public function findByUuid(
        string $uuid
    ): ?Category {

        return $this->repository
            ->findByUuid($uuid);
    }

    public function findByCode(
        string $code
    ): ?Category {

        return $this->repository
            ->findByCode(
                $code
            );
    }
}