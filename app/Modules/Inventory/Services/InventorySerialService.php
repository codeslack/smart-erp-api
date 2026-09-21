<?php

namespace App\Modules\Inventory\Services;

use Illuminate\Support\Collection;

use App\Core\Exceptions\BusinessException;

use App\Modules\Inventory\Enums\ProductSerialStatusEnum;
use App\Modules\Inventory\Models\ProductSerial;

use App\Modules\Inventory\Repositories\Contracts\ProductSerialRepositoryInterface;

class InventorySerialService
{
    public function __construct(
        protected ProductSerialRepositoryInterface $serials,
    ) {}

    public function findBySerialNumber(
        string $serialNumber
    ): ?ProductSerial {
        return $this->serials->findBySerialNumber(
            trim($serialNumber)
        );
    }

    public function findAvailable(
        int $productId,
        int $warehouseId,
        ?int $productVariantId = null
    ): Collection {
        return $this->serials->findAvailable(
            productId: $productId,
            warehouseId: $warehouseId,
            productVariantId: $productVariantId,
        );
    }

    public function activate(
        ProductSerial $serial
    ): ProductSerial {
        $this->validateStatus(
            serial: $serial,
            expected: ProductSerialStatusEnum::DRAFT,
            message:
                'Only draft serials can be activated.'
        );

        return $this->serials->update(
            $serial,
            [
                'status' =>
                    ProductSerialStatusEnum::AVAILABLE,
            ]
        );
    }

    public function deleteDraft(
        ProductSerial $serial
    ): bool {
        if (
            $serial->status
            !== ProductSerialStatusEnum::DRAFT
        ) {
            throw new BusinessException(
                'Only draft serials can be deleted.'
            );
        }

        return $this->serials->delete(
            $serial
        );
    }

    public function lock(
        int $serialId
    ): ProductSerial {
        return $this->serials->findForUpdate(
            $serialId
        );
    }

    public function lockForStockIn(
        int $serialId,
        int $productId,
        int $warehouseId,
        ?int $productVariantId = null,
        ?int $productBatchId = null,
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
            expected: ProductSerialStatusEnum::AVAILABLE,
            message:
                'Only an available serial can be added to stock.'
        );

        return $serial;
    }

    public function lockForStockOut(
        int $serialId,
        int $productId,
        int $warehouseId,
        ?int $productVariantId = null,
        ?int $productBatchId = null,
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
            expected: ProductSerialStatusEnum::AVAILABLE,
            message:
                'Only an available serial can be sold.'
        );

        return $serial;
    }

    public function register(
        int $productId,
        int $warehouseId,
        string $serialNumber,
        float $purchaseCost,
        ?int $productVariantId = null,
        ?int $productBatchId = null,
        ?string $imeiNumber = null,
        ?\DateTimeInterface $warrantyExpiry = null,
        ?string $sourceableType = null,
        ?int $sourceableId = null,
        ?string $remarks = null,
    ): ProductSerial {
        return $this->registerWithStatus(
            productId: $productId,
            warehouseId: $warehouseId,
            serialNumber: $serialNumber,
            purchaseCost: $purchaseCost,
            status: ProductSerialStatusEnum::AVAILABLE,
            productVariantId: $productVariantId,
            productBatchId: $productBatchId,
            imeiNumber: $imeiNumber,
            warrantyExpiry: $warrantyExpiry,
            sourceableType: $sourceableType,
            sourceableId: $sourceableId,
            remarks: $remarks,
        );
    }

    public function registerDraft(
        int $productId,
        int $warehouseId,
        string $serialNumber,
        float $purchaseCost,
        ?int $productVariantId = null,
        ?int $productBatchId = null,
        ?string $imeiNumber = null,
        ?\DateTimeInterface $warrantyExpiry = null,
        ?string $sourceableType = null,
        ?int $sourceableId = null,
        ?string $remarks = null,
    ): ProductSerial {
        return $this->registerWithStatus(
            productId: $productId,
            warehouseId: $warehouseId,
            serialNumber: $serialNumber,
            purchaseCost: $purchaseCost,
            status: ProductSerialStatusEnum::DRAFT,
            productVariantId: $productVariantId,
            productBatchId: $productBatchId,
            imeiNumber: $imeiNumber,
            warrantyExpiry: $warrantyExpiry,
            sourceableType: $sourceableType,
            sourceableId: $sourceableId,
            remarks: $remarks,
        );
    }

    protected function registerWithStatus(
        int $productId,
        int $warehouseId,
        string $serialNumber,
        float $purchaseCost,
        ProductSerialStatusEnum $status,
        ?int $productVariantId = null,
        ?int $productBatchId = null,
        ?string $imeiNumber = null,
        ?\DateTimeInterface $warrantyExpiry = null,
        ?string $sourceableType = null,
        ?int $sourceableId = null,
        ?string $remarks = null,
    ): ProductSerial {
        $serialNumber = trim($serialNumber);

        if ($serialNumber === '') {
            throw new BusinessException(
                'Serial number is required.'
            );
        }

        if ($purchaseCost < 0) {
            throw new BusinessException(
                'Serial purchase cost cannot be negative.'
            );
        }

        if (
            $this->findBySerialNumber(
                $serialNumber
            ) !== null
        ) {
            throw new BusinessException(
                'Serial number already exists.'
            );
        }

        return $this->serials->register([
            'product_id' => $productId,
            'product_variant_id' => $productVariantId,
            'warehouse_id' => $warehouseId,
            'product_batch_id' => $productBatchId,
            'serial_number' => $serialNumber,
            'imei_number' => $imeiNumber,
            'purchase_cost' => $purchaseCost,
            'warranty_expiry' => $warrantyExpiry,
            'status' => $status,
            'sourceable_type' => $sourceableType,
            'sourceable_id' => $sourceableId,
            'remarks' => $remarks,
        ]);
    }

    public function restoreDraft(
        ProductSerial $serial
    ): ProductSerial {
        if (
            $serial->status
            !== ProductSerialStatusEnum::AVAILABLE
        ) {
            throw new BusinessException(
                'Only available serial can be restored to draft.'
            );
        }

        return $this->serials->update(
            $serial,
            [
                'status' =>
                    ProductSerialStatusEnum::DRAFT,

                'sold_at' => null,
            ]
        );
    }

    public function markSold(
        ProductSerial $serial
    ): void {
        $this->validateStatus(
            serial: $serial,
            expected: ProductSerialStatusEnum::AVAILABLE,
            message:
                'Only an available serial can be sold.'
        );

        $this->serials->markSold($serial);
    }

    public function markAvailable(
        ProductSerial $serial
    ): void {
        $this->validateStatus(
            serial: $serial,
            expected: ProductSerialStatusEnum::SOLD,
            message:
                'Only a sold serial can be made available.'
        );

        $this->serials->markAvailable($serial);
    }

    public function markReturned(
        ProductSerial $serial
    ): void {
        if (! in_array(
            $serial->status,
            [
                ProductSerialStatusEnum::SOLD,
                ProductSerialStatusEnum::DAMAGED,
            ],
            true
        )) {
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
        ?int $productVariantId,
        ?int $productBatchId,
    ): void {
        if ($serial->product_id !== $productId) {
            throw new BusinessException(
                'Serial does not belong to the selected product.',
                'INVALID_SERIAL_PRODUCT'
            );
        }

        if ($serial->warehouse_id !== $warehouseId) {
            throw new BusinessException(
                'Serial does not belong to the selected warehouse.',
                'INVALID_SERIAL_WAREHOUSE'
            );
        }

        if (
            $serial->product_variant_id
            !== $productVariantId
        ) {
            throw new BusinessException(
                'Serial does not belong to the selected product variant.',
                'INVALID_SERIAL_VARIANT'
            );
        }

        if (
            $productBatchId !== null
            && $serial->product_batch_id !== $productBatchId
        ) {
            throw new BusinessException(
                'Serial does not belong to the selected batch.',
                'INVALID_SERIAL_BATCH'
            );
        }
    }

    protected function validateStatus(
        ProductSerial $serial,
        ProductSerialStatusEnum $expected,
        string $message
    ): void {
        if ($serial->status !== $expected) {
            throw new BusinessException($message);
        }
    }
}
