<?php

namespace App\Modules\Product\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

use App\Core\Repositories\BaseRepository;

use App\Modules\Product\Models\Product;

use App\Modules\Product\Repositories\Contracts\ProductRepositoryInterface;

/**
 * @extends BaseRepository<Product>
 */
class ProductRepository extends BaseRepository
implements ProductRepositoryInterface
{
    public function __construct(
        Product $model
    ) {
        parent::__construct(
            $model
        );
    }

    public function active(): Builder
    {
        return $this->model
            ->newQuery()
            ->active();
    }

    public function findByUuid(
        string $uuid
    ): ?Product {

        return $this->model
            ->newQuery()
            ->where(
                'uuid',
                $uuid
            )
            ->first();
    }

    public function findByCode(
        string $code
    ): ?Product {

        return $this->model
            ->newQuery()
            ->where(
                'code',
                $code
            )
            ->first();
    }

    public function findBySku(
        string $sku
    ): ?Product {

        return $this->model
            ->newQuery()
            ->where(
                'sku',
                $sku
            )
            ->first();
    }

    public function findByBarcode(
        string $barcode
    ): ?Product {

        return $this->model
            ->newQuery()
            ->where(
                'barcode',
                $barcode
            )
            ->first();
    }

    public function findBySlug(
        string $slug
    ): ?Product {

        return $this->model
            ->newQuery()
            ->where(
                'slug',
                $slug
            )
            ->first();
    }

    public function existsByCode(
        string $code,
        ?int $ignoreId = null
    ): bool {

        return $this->model
            ->newQuery()
            ->where(
                'code',
                $code
            )
            ->when(
                $ignoreId,
                fn(Builder $query) => $query->where(
                    'id',
                    '!=',
                    $ignoreId
                )
            )
            ->exists();
    }

    public function existsBySku(
        string $sku,
        ?int $ignoreId = null
    ): bool {

        return $this->model
            ->newQuery()
            ->where(
                'sku',
                $sku
            )
            ->when(
                $ignoreId,
                fn(Builder $query) => $query->where(
                    'id',
                    '!=',
                    $ignoreId
                )
            )
            ->exists();
    }

    public function existsByBarcode(
        string $barcode,
        ?int $ignoreId = null
    ): bool {

        return $this->model
            ->newQuery()
            ->where(
                'barcode',
                $barcode
            )
            ->when(
                $ignoreId,
                fn(Builder $query) => $query->where(
                    'id',
                    '!=',
                    $ignoreId
                )
            )
            ->exists();
    }

    public function existsBySlug(
        string $slug,
        ?int $ignoreId = null
    ): bool {

        return $this->model
            ->newQuery()
            ->where(
                'slug',
                $slug
            )
            ->when(
                $ignoreId,
                fn(Builder $query) => $query->where(
                    'id',
                    '!=',
                    $ignoreId
                )
            )
            ->exists();
    }

    public function search(
        ?string $search,
        int $perPage = 15
    ): LengthAwarePaginator {

        return $this->model
            ->newQuery()

            ->with([
                'category',
                'brand',
                'unit',
            ])

            ->when(
                filled($search),
                function (
                    Builder $query
                ) use (
                    $search
                ) {

                    $query->where(
                        function (
                            Builder $query
                        ) use (
                            $search
                        ) {

                            $query->where(
                                'name',
                                'like',
                                "%{$search}%"
                            )

                                ->orWhere(
                                    'code',
                                    'like',
                                    "%{$search}%"
                                )

                                ->orWhere(
                                    'sku',
                                    'like',
                                    "%{$search}%"
                                )

                                ->orWhere(
                                    'barcode',
                                    'like',
                                    "%{$search}%"
                                )

                                ->orWhere(
                                    'slug',
                                    'like',
                                    "%{$search}%"
                                );
                        }
                    );
                }
            )

            ->latest('id')

            ->paginate(
                $perPage
            );
    }

    public function paginateWithRelations(
        int $perPage = 15
    ): LengthAwarePaginator {

        return $this->model
            ->newQuery()

            ->with([
                'category',
                'brand',
                'unit',
            ])

            ->latest('id')

            ->paginate(
                $perPage
            );
    }
}
