<?php

namespace App\Modules\Product\Repositories\Contracts;

use App\Core\Repositories\Contracts\BaseRepositoryInterface;
use App\Modules\Product\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

interface ProductRepositoryInterface
    extends BaseRepositoryInterface
{
    public function active(): Builder;
    
    public function findBySku(
        string $sku
    ): ?Product;

    public function findByBarcode(
        string $barcode
    ): ?Product;

    public function findBySlug(
        string $slug
    ): ?Product;

    public function existsBySku(
        string $sku,
        ?int $ignoreId = null
    ): bool;

    public function existsByBarcode(
        string $barcode,
        ?int $ignoreId = null
    ): bool;
    
    public function search(
        ?string $search,
        int $perPage = 15
    ): LengthAwarePaginator;

    public function paginateWithRelations(
        int $perPage = 15
    ): LengthAwarePaginator;
}
