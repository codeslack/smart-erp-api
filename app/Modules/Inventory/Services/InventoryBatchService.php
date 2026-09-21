<?php

namespace App\Modules\Inventory\Services;

use Illuminate\Support\Collection;

use App\Core\Exceptions\BusinessException;

use App\Modules\Inventory\Models\ProductBatch;
use App\Modules\Inventory\Enums\ProductBatchStatusEnum;

use App\Modules\Inventory\Repositories\Contracts\ProductBatchRepositoryInterface;

class InventoryBatchService
{
    public function __construct(
        protected ProductBatchRepositoryInterface $batches,
    ) {}

    public function findAvailable(
        int $productId,
        int $warehouseId,
        ?int $productVariantId = null
    ): Collection {
        return $this->batches->findAvailable(
            $productId,
            $warehouseId,
            $productVariantId
        );
    }

    public function findByBatchNumber(
        string $batchNumber,
        int $productId,
        int $warehouseId,
        ?int $productVariantId = null
    ): ?ProductBatch {
        return $this->batches->findByBatchNumber(
            $batchNumber,
            $productId,
            $warehouseId,
            $productVariantId
        );
    }

    public function lock(
        int $batchId
    ): ProductBatch {
        return $this->batches->findForUpdate(
            $batchId
        );
    }

    public function createDraft(
        array $data
    ): ProductBatch {
        $batchNumber = trim(
            (string) ($data['batch_no'] ?? '')
        );

        if ($batchNumber === '') {
            throw new BusinessException(
                'Batch number is required.'
            );
        }

        $productId = (int) $data['product_id'];
        $warehouseId = (int) $data['warehouse_id'];

        $productVariantId =
            $data['product_variant_id'] ?? null;

        if (
            $this->findByBatchNumber(
                batchNumber: $batchNumber,
                productId: $productId,
                warehouseId: $warehouseId,
                productVariantId: $productVariantId,
            ) !== null
        ) {
            throw new BusinessException(
                'Batch number already exists.'
            );
        }

        $unitCost = (float) (
            $data['unit_cost'] ?? 0
        );

        if ($unitCost < 0) {
            throw new BusinessException(
                'Batch unit cost cannot be negative.'
            );
        }

        return $this->batches->create([
            'product_id' => $productId,
            'product_variant_id' => $productVariantId,
            'warehouse_id' => $warehouseId,
            'batch_no' => $batchNumber,
            'manufacturing_date' =>
                $data['manufacturing_date'] ?? null,
            'expiry_date' =>
                $data['expiry_date'] ?? null,
            'received_at' =>
                $data['received_at'] ?? null,
            'unit_cost' => $unitCost,
            'original_quantity' => 0,
            'remaining_quantity' => 0,
            'status' =>
                ProductBatchStatusEnum::DRAFT,
            'sourceable_type' =>
                $data['sourceable_type'] ?? null,
            'sourceable_id' =>
                $data['sourceable_id'] ?? null,
            'remarks' =>
                $data['remarks'] ?? null,
        ]);
    }

    public function restoreDraft(
        ProductBatch $batch
    ): ProductBatch {
        if (
            $batch->status
            !== ProductBatchStatusEnum::ACTIVE
        ) {
            throw new BusinessException(
                'Only active batch can be restored to draft.'
            );
        }

        if ((float) $batch->remaining_quantity !== 0.0) {
            throw new BusinessException(
                'Batch cannot be restored to draft while quantity remains.'
            );
        }

        return $this->batches->update(
            $batch,
            [
                'status' =>
                    ProductBatchStatusEnum::DRAFT,
                
                'original_quantity' => 0,
                'remaining_quantity' => 0,
            ]
        );
    }

    public function activate(
        ProductBatch $batch
    ): ProductBatch {
        if (
            $batch->status
            !== ProductBatchStatusEnum::DRAFT
        ) {
            throw new BusinessException(
                'Only draft batches can be activated.'
            );
        }

        return $this->batches->update(
            $batch,
            [
                'status' =>
                    ProductBatchStatusEnum::ACTIVE,
            ]
        );
    }

    public function deleteDraft(
        ProductBatch $batch
    ): bool {
        if (
            $batch->status
            !== ProductBatchStatusEnum::DRAFT
        ) {
            throw new BusinessException(
                'Only draft batches can be deleted.'
            );
        }

        return $this->batches->delete(
            $batch
        );
    }

    public function initializeOriginalQuantity(
        ProductBatch $batch,
        float $quantity
    ): void {
        $this->validateQuantity($quantity);

        if ((float) $batch->original_quantity !== 0.0) {
            throw new BusinessException(
                'Batch original quantity has already been initialized.'
            );
        }

        $this->batches->initializeOriginalQuantity(
            $batch,
            $quantity
        );
    }

    public function increase(
        ProductBatch $batch,
        float $quantity
    ): void {
        $this->validateQuantity($quantity);

        $this->batches->increaseQuantity(
            $batch,
            $quantity
        );
    }

    public function decrease(
        ProductBatch $batch,
        float $quantity
    ): void {
        $this->validateQuantity($quantity);

        if (
            $batch->remaining_quantity
            < $quantity
        ) {
            throw new BusinessException(
                'Insufficient batch quantity.'
            );
        }

        $this->batches->decreaseQuantity(
            $batch,
            $quantity
        );
    }

    protected function validateQuantity(
        float $quantity
    ): void {
        if ($quantity <= 0) {
            throw new BusinessException(
                'Batch quantity must be greater than zero.'
            );
        }
    }
}
