<?php

namespace App\Modules\Inventory\Services\Serial;

use DateTimeInterface;

use App\Core\Exceptions\BusinessException;

use App\Modules\Inventory\Enums\ProductSerialStatusEnum;
use App\Modules\Inventory\Models\ProductSerial;
use App\Modules\Inventory\Repositories\Contracts\ProductSerialRepositoryInterface;

class InventorySerialRegistrationService
{
    public function __construct(
        protected ProductSerialRepositoryInterface $serials,
    ) {}

    public function register(
        int $productId,
        int $warehouseId,
        string $serialNumber,
        float $purchaseCost,
        ?int $productVariantId = null,
        ?int $productBatchId = null,
        ?string $imeiNumber = null,
        ?DateTimeInterface $warrantyExpiry = null,
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
        ?DateTimeInterface $warrantyExpiry = null,
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
        ?DateTimeInterface $warrantyExpiry = null,
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
            $this->serials->findBySerialNumber($serialNumber) !== null
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
}