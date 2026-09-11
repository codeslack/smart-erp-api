<?php

namespace App\Modules\Inventory\Repositories\Contracts;

use App\Modules\Inventory\Models\InventoryCostLayer;

interface InventoryCostLayerRepositoryInterface
{
    /**
     * Create a new cost layer.
     */
    public function create(
        array $data
    ): InventoryCostLayer;

    /**
     * Get available FIFO layers.
     *
     * Ordered oldest first.
     */
    public function availableLayers(
        int $productId,
        ?int $productVariantId,
        int $warehouseId
    );

    /**
     * Get a specific cost layer.
     */
    public function findById(
        int $id
    ): ?InventoryCostLayer;

    public function findByStockLedgerId(
        int $stockLedgerId
    ): ?InventoryCostLayer;

    /**
     * Update a cost layer.
     */
    public function update(
        InventoryCostLayer $layer,
        array $data
    ): InventoryCostLayer;

    /**
     * Delete a cost layer.
     */
    public function delete(
        InventoryCostLayer $layer
    ): bool;
}