<?php

namespace App\Modules\Product\Repositories\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

use App\Core\Repositories\Contracts\BaseRepositoryInterface;

use App\Modules\Product\Models\ProductVariant;

interface ProductVariantRepositoryInterface
    extends BaseRepositoryInterface
{
    public function active(): Builder;

    public function findByCode(
        string $code
    ): ?ProductVariant;

    public function findBySku(
        string $sku
    ): ?ProductVariant;

    public function findByBarcode(
        string $barcode
    ): ?ProductVariant;

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

    public function paginateWithRelations(
        int $perPage = 15
    ): LengthAwarePaginator;

    public function search(
        ?string $search,
        int $perPage = 15
    ): LengthAwarePaginator;
}