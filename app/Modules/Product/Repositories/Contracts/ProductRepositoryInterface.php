<?php

namespace App\Modules\Product\Repositories\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

use App\Core\Repositories\Contracts\BaseRepositoryInterface;

use App\Modules\Product\Models\Product;

interface ProductRepositoryInterface
extends BaseRepositoryInterface
{
    public function active(): Builder;

    public function findByUuid(
        string $uuid
    ): ?Product;

    public function findByCode(
        string $code
    ): ?Product;

    public function findBySku(
        string $sku
    ): ?Product;

    public function findByBarcode(
        string $barcode
    ): ?Product;

    public function findBySlug(
        string $slug
    ): ?Product;

    public function existsByCode(
        string $code,
        ?int $ignoreId = null
    ): bool;

    public function existsBySku(
        string $sku,
        ?int $ignoreId = null
    ): bool;

    public function existsByBarcode(
        string $barcode,
        ?int $ignoreId = null
    ): bool;

    public function existsBySlug(
        string $slug,
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
