<?php

namespace App\Modules\ProductUnit\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;

use App\Core\Repositories\Contracts\BaseRepositoryInterface;

use App\Modules\ProductUnit\Models\ProductUnit;

interface ProductUnitRepositoryInterface
    extends BaseRepositoryInterface
{
    /**
     * Get all UOMs configured for a product.
     */
    public function getByProductId(
        int $productId
    ): Collection;

    /**
     * Find a specific product-unit configuration.
     */
    public function findByProductAndUnit(
        int $productId,
        int $unitId
    ): ?ProductUnit;

    /**
     * Get the active UOMs available for purchase.
     */
    public function getPurchaseUnits(
        int $productId
    ): Collection;

    /**
     * Get the active UOMs available for sales.
     */
    public function getSalesUnits(
        int $productId
    ): Collection;

    /**
     * Get the configured base-unit relationship.
     *
     * The actual authoritative base unit is
     * products.unit_id.
     */
    public function findBaseUnit(
        int $productId
    ): ?ProductUnit;
}