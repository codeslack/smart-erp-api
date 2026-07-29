<?php

namespace App\Modules\Product\Repositories;

use App\Core\Repositories\BaseRepository;
use App\Modules\Product\Enums\ProductStatusEnum;
use App\Modules\Product\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Modules\Product\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<Product>
 */
class ProductRepository 
    extends BaseRepository 
    implements ProductRepositoryInterface
{
    public function __construct(Product $product)
    {
        $this->model = $product;
    }

    public function active(): Builder
    {
        return $this->model
            ->newQuery()
            ->where(
                'status',
                ProductStatusEnum::ACTIVE->value
            );
    }

    public function search(
        ?string $search,
        int $perPage = 15
    ): LengthAwarePaginator {

        return $this->model
            ->newQuery()
            ->with($this->relations())
            ->when(
                $search,
                fn ($query) => $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
                })
            )
            ->latest()
            ->paginate($perPage);
    }

    public function findBySku(
        string $sku
    ): ?Product {
        return $this->model
            ->newQuery()
            ->where('sku', $sku)
            ->first();
    }

    public function findByBarcode(
        string $barcode
    ): ?Product {
        return $this->model
            ->newQuery()
            ->where('barcode', $barcode)
            ->first();
    }

    public function findBySlug(
        string $slug
    ): ?Product {
        return $this->model
            ->newQuery()
            ->where('slug', $slug)
            ->first();
    }

    public function existsBySku(
        string $sku,
        ?int $ignoreId = null
    ): bool {

        $query = $this->model
            ->newQuery()
            ->where('sku', $sku);

        if ($ignoreId) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->exists();
    }

    public function existsByBarcode(
        string $barcode,
        ?int $ignoreId = null
    ): bool {

        $query = $this->model
            ->newQuery()
            ->where('barcode', $barcode);

        if ($ignoreId) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->exists();
    }

    public function paginateWithRelations(
        int $perPage = 15
    ): LengthAwarePaginator {

        return $this->model
            ->newQuery()
            ->with($this->relations())
            ->latest()
            ->paginate($perPage);
    }

    private function relations(): array
    {
        return [
            'category',
            'brand',
            'unit',
        ];
    }
}
