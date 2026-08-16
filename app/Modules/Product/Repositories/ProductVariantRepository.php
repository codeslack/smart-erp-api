<?php

namespace App\Modules\Product\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

use App\Core\Repositories\BaseRepository;

use App\Modules\Product\Models\ProductVariant;

use App\Modules\Product\Repositories\Contracts\ProductVariantRepositoryInterface;

/**
 * @extends BaseRepository<ProductVariant>
 */
class ProductVariantRepository extends BaseRepository
    implements ProductVariantRepositoryInterface
{
    public function __construct(
        ProductVariant $model
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

    public function findByCode(
        string $code
    ): ?ProductVariant {

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
    ): ?ProductVariant {

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
    ): ?ProductVariant {

        return $this->model
            ->newQuery()
            ->where(
                'barcode',
                $barcode
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
                fn (Builder $query) => $query->where(
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
                fn (Builder $query) => $query->where(
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
                fn (Builder $query) => $query->where(
                    'id',
                    '!=',
                    $ignoreId
                )
            )
            ->exists();
    }

    public function paginateWithRelations(
        int $perPage = 15
    ): LengthAwarePaginator {

        return $this->model
            ->newQuery()

            ->with([
                'product',
                'attributes',
            ])

            ->latest('id')

            ->paginate(
                $perPage
            );
    }

    public function search(
        ?string $search,
        int $perPage = 15
    ): LengthAwarePaginator {

        return $this->model
            ->newQuery()

            ->with([
                'product',
                'attributes',
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
}