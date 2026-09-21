<?php

namespace App\Modules\OpeningStock\Services;

use Carbon\CarbonImmutable;

use App\Core\Exceptions\BusinessException;

use App\Modules\Product\Models\Product;

use App\Modules\Inventory\Models\ProductBatch;
use App\Modules\Inventory\Models\ProductSerial;

use App\Modules\Inventory\Services\InventoryBatchService;
use App\Modules\Inventory\Services\InventorySerialService;

use App\Modules\OpeningStock\Models\OpeningStock;
use App\Modules\OpeningStock\Models\OpeningStockItem;
use App\Modules\OpeningStock\Models\OpeningStockSource;

use App\Modules\Product\Repositories\Contracts\ProductRepositoryInterface;
use App\Modules\Inventory\Repositories\Contracts\ProductBatchRepositoryInterface;
use App\Modules\Inventory\Repositories\Contracts\ProductSerialRepositoryInterface;

class OpeningStockItemService
{
    public function __construct(

        protected ProductRepositoryInterface $productsRepository,

        protected ProductBatchRepositoryInterface $batchRepository,
        protected ProductSerialRepositoryInterface $serialRepository,

        protected InventoryBatchService $batchService,
        protected InventorySerialService $serialService,
    ) {}

    public function createItems(
        OpeningStockSource $source,
        array $items
    ): void {
        $openingStock = $source->openingStock;

        foreach ($items as $item) {
            $product = $this->findProduct(
                (int) $item['product_id']
            );

            $this->validateItem(
                product: $product,
                item: $item
            );

            $batchId = null;
            $serialId = null;

            if ($product->isBatchTracked()) {
                $batch = $this->createBatch(
                    product: $product,
                    openingStock: $openingStock,
                    item: $item
                );

                $batchId = $batch->id;
            }

            if ($product->isSerialTracked()) {
                $serial = $this->createSerial(
                    product: $product,
                    openingStock: $openingStock,
                    item: $item
                );

                $serialId = $serial->id;
            }

            $quantity = (float) $item['quantity'];
            $unitCost = (float) $item['unit_cost'];

            $source->items()->create([
                'product_id' => $product->id,
                'product_variant_id' =>
                    $item['product_variant_id'] ?? null,
                'product_batch_id' => $batchId,
                'product_serial_id' => $serialId,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'total_cost' => $quantity * $unitCost,
                'remarks' => $item['remarks'] ?? null,
            ]);
        }
    }

    public function replaceItems(
        OpeningStockSource $source,
        array $items
    ): void {
        $this->deleteItems($source);

        $this->createItems(
            source: $source,
            items: $items
        );
    }

    public function deleteItems(
        OpeningStockSource $source
    ): void {
        $source->loadMissing('items');

        foreach ($source->items as $item) {
            $this->deleteInventoryRecord($item);
        }

        $source->items()->delete();
    }

    protected function deleteInventoryRecord(
        OpeningStockItem $item
    ): void {
        if ($item->product_batch_id !== null) {

            $batch = $this->batchRepository->findById(
                $item->product_batch_id
            );

            if ($batch !== null) {
                $this->batchService->deleteDraft($batch);
            }
        }

        if ($item->product_serial_id !== null) {

            $serial = $this->serialRepository->findById(
                $item->product_serial_id
            );

            if ($serial !== null) {
                $this->serialService->deleteDraft($serial);
            }
        }
    }

    protected function findProduct(
        int $productId
    ): Product {
        $product = $this->productsRepository->findById(
            $productId
        );

        if (! $product instanceof Product) {
            throw new BusinessException(
                'Product not found.'
            );
        }

        return $product;
    }

    protected function validateItem(
        Product $product,
        array $item
    ): void {
        $batchNo = trim(
            (string) ($item['batch_no'] ?? '')
        );

        $serialNumber = trim(
            (string) ($item['serial_number'] ?? '')
        );

        if ($product->isBatchTracked()) {
            if ($batchNo === '') {
                throw new BusinessException(
                    'Batch number is required for batch-tracked products.'
                );
            }

            if (
                $serialNumber !== ''
                || ! empty($item['imei_number'])
            ) {
                throw new BusinessException(
                    'Serial information is not allowed for batch-tracked products.'
                );
            }

            if (
                $product->hasExpiryTracking()
                && empty($item['expiry_date'])
            ) {
                throw new BusinessException(
                    'Expiry date is required for this product.'
                );
            }

            return;
        }

        if ($product->isSerialTracked()) {
            if ($serialNumber === '') {
                throw new BusinessException(
                    'Serial number is required for serial-tracked products.'
                );
            }

            if ((float) $item['quantity'] !== 1.0) {
                throw new BusinessException(
                    'Serial-tracked item quantity must be exactly 1.'
                );
            }

            if (
                $batchNo !== ''
                || ! empty($item['manufacturing_date'])
                || ! empty($item['expiry_date'])
            ) {
                throw new BusinessException(
                    'Batch information is not allowed for serial-tracked products.'
                );
            }

            if (
                $product->hasWarrantyTracking()
                && empty($item['warranty_expiry'])
            ) {
                throw new BusinessException(
                    'Warranty expiry is required for this product.'
                );
            }

            return;
        }

        if (
            $batchNo !== ''
            || $serialNumber !== ''
            || ! empty($item['imei_number'])
            || ! empty($item['manufacturing_date'])
            || ! empty($item['expiry_date'])
            || ! empty($item['warranty_expiry'])
        ) {
            throw new BusinessException(
                'Batch or serial information is not allowed for this product.'
            );
        }
    }

    protected function createBatch(
        Product $product,
        OpeningStock $openingStock,
        array $item
    ): ProductBatch {
        return $this->batchService->createDraft([
            'product_id' => $product->id,
            'product_variant_id' =>
                $item['product_variant_id'] ?? null,
            'warehouse_id' =>
                $openingStock->warehouse_id,
            'batch_no' => $item['batch_no'],
            'manufacturing_date' =>
                $item['manufacturing_date'] ?? null,
            'expiry_date' =>
                $item['expiry_date'] ?? null,
            'unit_cost' => (float) $item['unit_cost'],
            'sourceable_type' => OpeningStock::class,
            'sourceable_id' => $openingStock->id,
            'remarks' => $item['remarks'] ?? null,
        ]);
    }

    protected function createSerial(
        Product $product,
        OpeningStock $openingStock,
        array $item
    ): ProductSerial {
        $warrantyExpiry = ! empty($item['warranty_expiry'])
            ? CarbonImmutable::parse(
                $item['warranty_expiry']
            )
            : null;

        return $this->serialService->registerDraft(
            productId: $product->id,
            warehouseId: $openingStock->warehouse_id,
            serialNumber: $item['serial_number'],
            purchaseCost: (float) $item['unit_cost'],
            productVariantId:
                $item['product_variant_id'] ?? null,
            imeiNumber:
                $item['imei_number'] ?? null,
            warrantyExpiry: $warrantyExpiry,
            sourceableType: OpeningStock::class,
            sourceableId: $openingStock->id,
            remarks: $item['remarks'] ?? null,
        );
    }
}