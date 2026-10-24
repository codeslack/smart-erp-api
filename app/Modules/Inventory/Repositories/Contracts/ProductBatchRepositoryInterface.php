<?php

namespace App\Modules\Inventory\Repositories\Contracts;

use Illuminate\Support\Collection;

use App\Modules\Inventory\Models\ProductBatch;

use App\Core\Repositories\Contracts\BaseRepositoryInterface;

interface ProductBatchRepositoryInterface
    extends BaseRepositoryInterface
{
    public function findAvailable(
        int $productId,
        int $warehouseId,
        ?int $productVariantId = null
    ): Collection;

    public function findByBatchNumber(
        string $batchNumber,
        int $productId,
        int $warehouseId,
        ?int $productVariantId = null
    ): ?ProductBatch;

    public function findByBatchNumberForUpdate(
        string $batchNumber,
        int $productId,
        int $warehouseId,
    ): ?ProductBatch;

    public function findForUpdate(
        int $id
    ): ProductBatch;

    /** @return Collection<int, ProductBatch> */
    public function findBySourceForUpdate(
        string $sourceableType,
        int $sourceableId,
    ): Collection;

    public function initializeOriginalQuantity(
        ProductBatch $batch,
        float $quantity
    ): void;

    public function increaseQuantity(
        ProductBatch $batch,
        float $quantity
    ): void;

    public function decreaseQuantity(
        ProductBatch $batch,
        float $quantity
    ): void;
}