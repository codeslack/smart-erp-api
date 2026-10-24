<?php

namespace App\Modules\Inventory\Services\Serial;

use App\Core\Exceptions\BusinessException;

use App\Modules\Inventory\Enums\ProductSerialStatusEnum;
use App\Modules\Inventory\Models\ProductSerial;
use App\Modules\Inventory\Repositories\Contracts\ProductSerialRepositoryInterface;

class InventorySerialMovementService
{
    public function __construct(
        protected ProductSerialRepositoryInterface $serials,
    ) {}

    public function lock(int $serialId): ProductSerial
    {
        return $this->serials->findForUpdate($serialId);
    }

    public function lockForStockIn(
        int $serialId,
        int $productId,
        int $warehouseId,
        ?int $productVariantId = null,
        ?int $productBatchId = null,
    ): ProductSerial {
        return $this->lockForMovement(
            serialId: $serialId,
            productId: $productId,
            warehouseId: $warehouseId,
            productVariantId: $productVariantId,
            productBatchId: $productBatchId,
            expectedStatus: ProductSerialStatusEnum::AVAILABLE,
            statusMessage: 'Only available serials can be used for stock-in.',
        );
    }

    public function lockForStockOut(
        int $serialId,
        int $productId,
        int $warehouseId,
        ?int $productVariantId = null,
        ?int $productBatchId = null,
    ): ProductSerial {
        return $this->lockForMovement(
            serialId: $serialId,
            productId: $productId,
            warehouseId: $warehouseId,
            productVariantId: $productVariantId,
            productBatchId: $productBatchId,
            expectedStatus: ProductSerialStatusEnum::AVAILABLE,
            statusMessage: 'Only available serials can be used for stock-out.',
        );
    }

    protected function lockForMovement(
        int $serialId,
        int $productId,
        int $warehouseId,
        ?int $productVariantId,
        ?int $productBatchId,
        ProductSerialStatusEnum $expectedStatus,
        string $statusMessage,
    ): ProductSerial {
        $serial = $this->lock($serialId);

        $this->validateOwnership(
            serial: $serial,
            productId: $productId,
            warehouseId: $warehouseId,
            productVariantId: $productVariantId,
            productBatchId: $productBatchId,
        );

        $this->validateStatus(
            serial: $serial,
            expected: $expectedStatus,
            message: $statusMessage,
        );

        return $serial;
    }

    public function markSold(ProductSerial $serial): void
    {
        $this->validateStatus(
            serial: $serial,
            expected: ProductSerialStatusEnum::AVAILABLE,
            message: 'Only available serials can be sold.',
        );

        $this->serials->markSold($serial);
    }

    public function markAvailable(ProductSerial $serial): void
    {
        $this->validateStatus(
            serial: $serial,
            expected: ProductSerialStatusEnum::SOLD,
            message: 'Only sold serials can be made available.',
        );

        $this->serials->markAvailable($serial);
    }

    public function markReturned(ProductSerial $serial): void
    {
        if (! in_array($serial->status, [
            ProductSerialStatusEnum::SOLD,
            ProductSerialStatusEnum::DAMAGED,
        ], true)) {
            throw new BusinessException(
                'Only sold or damaged serials can be returned.'
            );
        }

        $this->serials->markReturned($serial);
    }

    protected function validateOwnership(
        ProductSerial $serial,
        int $productId,
        int $warehouseId,
        ?int $productVariantId = null,
        ?int $productBatchId = null,
    ): void {
        if ($serial->product_id !== $productId) {
            throw new BusinessException(
                'Serial does not belong to the selected product.',
                'INVALID_SERIAL_PRODUCT',
            );
        }

        if ($serial->warehouse_id !== $warehouseId) {
            throw new BusinessException(
                'Serial does not belong to the selected warehouse.',
                'INVALID_SERIAL_WAREHOUSE',
            );
        }

        if ($serial->product_variant_id !== $productVariantId) {
            throw new BusinessException(
                'Serial does not belong to the selected product variant.',
                'INVALID_SERIAL_VARIANT',
            );
        }

        if (
            $productBatchId !== null
            && $serial->product_batch_id !== $productBatchId
        ) {
            throw new BusinessException(
                'Serial does not belong to the selected product batch.',
                'INVALID_SERIAL_BATCH',
            );
        }
    }

    protected function validateStatus(
        ProductSerial $serial,
        ProductSerialStatusEnum $expected,
        string $message,
    ): void {
        if ($serial->status !== $expected) {
            throw new BusinessException($message);
        }
    }
}