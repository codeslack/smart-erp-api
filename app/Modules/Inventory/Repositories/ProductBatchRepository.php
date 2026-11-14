<?php

namespace App\Modules\Inventory\Repositories;

use Illuminate\Support\Collection;

use App\Core\Repositories\BaseRepository;

use App\Modules\Inventory\Models\ProductBatch;
use App\Modules\Inventory\Enums\ProductBatchStatusEnum;
use App\Modules\Inventory\Repositories\Contracts\ProductBatchRepositoryInterface;

class ProductBatchRepository
    extends BaseRepository
    implements ProductBatchRepositoryInterface
{
    public function __construct(
        ProductBatch $model
    ) {
        parent::__construct($model);
    }

    public function findAvailable(
        int $productId,
        int $warehouseId,
        ?int $productVariantId = null
    ): Collection {
        return $this->model
            ->newQuery()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->when(
                $productVariantId !== null,
                fn($query) => $query->where(
                    'product_variant_id',
                    $productVariantId
                ),
                fn($query) => $query->whereNull(
                    'product_variant_id'
                )
            )
            ->where('status', ProductBatchStatusEnum::ACTIVE->value)
            ->where('remaining_quantity', '>', 0)
            ->where(function ($query) {
                $query->whereNull('expiry_date')
                    ->orWhereDate('expiry_date', '>=', now()->toDateString());
            })
            ->get();
    }

    public function findByBatchNumber(
        string $batchNumber,
        int $productId,
        int $warehouseId,
        ?int $productVariantId = null
    ): ?ProductBatch {
        return $this->model
            ->newQuery()
            ->where('batch_no', $batchNumber)
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->first();
    }

    /**
     * Find a batch by its unique business identity using a
     * locking read for concurrent batch-creation recovery.
     */
    public function findByBatchNumberForUpdate(
        string $batchNumber,
        int $productId,
        int $warehouseId,
    ): ?ProductBatch {
        return $this->model
            ->newQuery()
            ->where('batch_no', $batchNumber)
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();
    }

    public function findForUpdate(
        int $id
    ): ProductBatch {
        return $this->model
            ->newQuery()
            ->lockForUpdate()
            ->findOrFail($id);
    }

    public function findBySourceForUpdate(
        string $sourceableType,
        int $sourceableId,
    ): Collection {
        return $this->model
            ->newQuery()
            ->where('sourceable_type', $sourceableType)
            ->where('sourceable_id', $sourceableId)
            ->lockForUpdate()
            ->get();
    }

    public function initializeOriginalQuantity(
        ProductBatch $batch,
        float $quantity
    ): void {
        $batch->update([
            'original_quantity' => $quantity,
        ]);

        $batch->refresh();
    }

    public function increaseQuantity(
        ProductBatch $batch,
        float $quantity
    ): void {
        $batch->increment(
            'remaining_quantity',
            $quantity
        );

        $batch->refresh();
    }

    public function decreaseQuantity(
        ProductBatch $batch,
        float $quantity
    ): void {
        $batch->decrement(
            'remaining_quantity',
            $quantity
        );
    }
}