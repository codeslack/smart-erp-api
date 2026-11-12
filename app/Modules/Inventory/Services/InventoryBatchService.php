<?php

namespace App\Modules\Inventory\Services;

use Illuminate\Support\Collection;
use Illuminate\Database\QueryException;

use App\Core\Exceptions\BusinessException;

use App\Modules\Inventory\Models\ProductBatch;
use App\Modules\Inventory\Enums\ProductBatchStatusEnum;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

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
        $batch = $this->batches->findByBatchNumber(
            $batchNumber,
            $productId,
            $warehouseId,
            $productVariantId
        );

        if ($batch === null) {
            return null;
        }

        $existingVariantId = $batch->product_variant_id !== null
            ? (int) $batch->product_variant_id
            : null;

        $requestedVariantId = $productVariantId !== null
            ? (int) $productVariantId
            : null;

        if ($existingVariantId !== $requestedVariantId) {
            throw new BusinessException(
                'Batch number already exists for this product and warehouse with a different variant.'
            );
        }

        return $batch;
    }

    /**
     * Find and lock a batch by its business identity.
     *
     * Intended for concurrency recovery when another transaction
     * has already created the same batch.
     */
    public function findByBatchNumberForUpdate(
        string $batchNumber,
        int $productId,
        int $warehouseId,
        ?int $productVariantId = null,
    ): ?ProductBatch {
        $batch = $this->batches->findByBatchNumberForUpdate(
            $batchNumber,
            $productId,
            $warehouseId,
        );

        $existingVariantId = $batch?->product_variant_id !== null
            ? (int) $batch->product_variant_id
            : null;

        $requestedVariantId = $productVariantId !== null
            ? (int) $productVariantId
            : null;

        if (
            $batch !== null
            && $existingVariantId !== $requestedVariantId
        ) {
            throw new BusinessException(
                'Batch number already exists for this product and warehouse with a different variant.'
            );
        }

        return $batch;
    }

    /**
     * Determine whether an insert failed because another request
     * created the same batch under the database unique constraint.
     *
     * Do not classify unrelated database errors as duplicate batches.
     */
    public function isDuplicateBatchException(
        QueryException $exception
    ): bool {
        $errorInfo = $exception->errorInfo
            ?? $exception->getPrevious()?->errorInfo
            ?? [];

        $errorCode = (int) (
            $errorInfo[1]
            ?? $exception->getPrevious()?->getCode()
            ?? 0
        );

        $message = strtolower(
            $exception->getMessage()
                . ' '
                . $exception->getPrevious()?->getMessage()
        );

        return $errorCode === 1062
            && str_contains(
                $message,
                'product_batches_unique_batch'
            );
    }

    public function lock(
        int $batchId
    ): ProductBatch {
        return $this->batches->findForUpdate($batchId);
    }

    /**
     * Ensure a batch belongs to the requested inventory movement.
     */
    public function validateOwnership(
        ProductBatch $batch,
        int $productId,
        int $warehouseId,
        ?int $productVariantId = null,
    ): void {
        $batchVariantId = $batch->product_variant_id !== null
            ? (int) $batch->product_variant_id
            : null;

        if (
            (int) $batch->product_id !== $productId
            || (int) $batch->warehouse_id !== $warehouseId
            || $batchVariantId !== $productVariantId
        ) {
            throw new BusinessException(
                'Batch does not belong to the specified product, warehouse, and variant.'
            );
        }
    }

    /**
     * Ensure a batch is active and not expired on the inventory transaction date.
     */
    public function validateAvailableForIssue(
        ProductBatch $batch,
        CarbonInterface|string $transactionDate,
    ): void {
        if ($batch->status !== ProductBatchStatusEnum::ACTIVE) {
            throw new BusinessException(
                'Only active batches can be issued from inventory.'
            );
        }

        $expiryDate = $batch->expiry_date?->toDateString();
        $issueDate = Carbon::parse($transactionDate)->toDateString();

        // Expiry date is inclusive: a batch remains valid through that day.
        if ($expiryDate !== null && $expiryDate < $issueDate) {
            throw new BusinessException(
                "Batch {$batch->batch_no} expired on {$expiryDate} and cannot be issued on {$issueDate}."
            );
        }
    }

    public function findBySourceForUpdate(
        string $sourceableType,
        int $sourceableId,
    ): Collection {
        return $this->batches->findBySourceForUpdate(
            $sourceableType,
            $sourceableId,
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

        $productId = (int) ($data['product_id'] ?? 0);
        $warehouseId = (int) ($data['warehouse_id'] ?? 0);

        $productVariantId = isset($data['product_variant_id'])
            ? (int) $data['product_variant_id']
            : null;

        $unitCost = (float) ($data['unit_cost'] ?? 0);

        if ($unitCost < 0) {
            throw new BusinessException(
                'Batch unit cost cannot be negative.'
            );
        }

        if (
            $this->findByBatchNumber(
                batchNumber: $batchNumber,
                productId: $productId,
                warehouseId: $warehouseId,
                productVariantId: $productVariantId,
            ) !== null
        ) {
            throw new BusinessException(
                'Batch number already exists for this product and warehouse.'
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
            'status' => ProductBatchStatusEnum::DRAFT,
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
                'status' => ProductBatchStatusEnum::DRAFT,
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
                'status' => ProductBatchStatusEnum::ACTIVE,
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

        return $this->batches->delete($batch);
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

        if ((float) $batch->remaining_quantity < $quantity) {
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
