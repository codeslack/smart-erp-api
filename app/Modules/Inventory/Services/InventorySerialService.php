<?php

namespace App\Modules\Inventory\Services;

use DateTimeInterface;
use Illuminate\Support\Collection;

use App\Modules\Inventory\Models\ProductSerial;

use App\Modules\Inventory\Repositories\Contracts\ProductSerialRepositoryInterface;

use App\Modules\Inventory\Services\Serial\InventorySerialLifecycleService;
use App\Modules\Inventory\Services\Serial\InventorySerialMovementService;
use App\Modules\Inventory\Services\Serial\InventorySerialRegistrationService;

class InventorySerialService
{
    public function __construct(
        protected ProductSerialRepositoryInterface $serials,
        protected InventorySerialRegistrationService $registrationService,
        protected InventorySerialMovementService $movementService,
        protected InventorySerialLifecycleService $lifecycleService,
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
        return $this->lifecycleService->activate($serial);
    }

    public function deleteDraft(
        ProductSerial $serial
    ): bool {
        return $this->lifecycleService->deleteDraft($serial);
    }

    public function lock(
        int $serialId
    ): ProductSerial {
        return $this->movementService->lock($serialId);
    }

    public function lockForStockIn(
        int $serialId,
        int $productId,
        int $warehouseId,
        ?int $productVariantId = null,
        ?int $productBatchId = null,
    ): ProductSerial {
        return $this->movementService->lockForStockIn(
            serialId: $serialId,
            productId: $productId,
            warehouseId: $warehouseId,
            productVariantId: $productVariantId,
            productBatchId: $productBatchId,
        );
    }

    public function lockForStockOut(
        int $serialId,
        int $productId,
        int $warehouseId,
        ?int $productVariantId = null,
        ?int $productBatchId = null,
    ): ProductSerial {
        return $this->movementService->lockForStockOut(
            serialId: $serialId,
            productId: $productId,
            warehouseId: $warehouseId,
            productVariantId: $productVariantId,
            productBatchId: $productBatchId,
        );
    }

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
        return $this->registrationService->register(
            productId: $productId,
            warehouseId: $warehouseId,
            serialNumber: $serialNumber,
            purchaseCost: $purchaseCost,
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
        return $this->registrationService->registerDraft(
            productId: $productId,
            warehouseId: $warehouseId,
            serialNumber: $serialNumber,
            purchaseCost: $purchaseCost,
            productVariantId: $productVariantId,
            productBatchId: $productBatchId,
            imeiNumber: $imeiNumber,
            warrantyExpiry: $warrantyExpiry,
            sourceableType: $sourceableType,
            sourceableId: $sourceableId,
            remarks: $remarks,
        );
    }

    public function restoreDraft(
        ProductSerial $serial
    ): ProductSerial {
        return $this->lifecycleService->restoreDraft($serial);
    }

    public function markSold(
        ProductSerial $serial
    ): void {
        $this->movementService->markSold($serial);
    }

    public function markAvailable(
        ProductSerial $serial
    ): void {
        $this->movementService->markAvailable($serial);
    }

    public function markReturned(
        ProductSerial $serial
    ): void {
        $this->movementService->markReturned($serial);
    }
}