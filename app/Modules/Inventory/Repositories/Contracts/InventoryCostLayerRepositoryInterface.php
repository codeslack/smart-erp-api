<?php

namespace App\Modules\Inventory\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;
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
    ): Collection;

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
     * Get a specific cost layer and lock it for update.
     *
     * Must be called inside a database transaction.
     */
    public function findForUpdate(
        int $id
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