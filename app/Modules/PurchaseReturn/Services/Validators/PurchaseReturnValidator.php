<?php

namespace App\Modules\PurchaseReturn\Services\Validators;

use App\Core\Exceptions\BusinessException;
use App\Modules\Inventory\Enums\ProductSerialStatusEnum;
use App\Modules\Inventory\Models\ProductBatch;
use App\Modules\Inventory\Models\ProductSerial;
use App\Modules\Product\Models\Product;
use App\Modules\Purchase\Enums\PurchaseStatusEnum;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Models\PurchaseItem;
use App\Modules\PurchaseReturn\Enums\PurchaseReturnRefundType;
use App\Modules\PurchaseReturn\Enums\PurchaseReturnStatus;
use App\Modules\PurchaseReturn\Models\PurchaseReturn;
use App\Modules\PurchaseReturn\Models\PurchaseReturnItem;
use Illuminate\Validation\ValidationException;

class PurchaseReturnValidator
{
    public function validateForSave(array $headerData, array $items, ?int $excludeReturnId = null): void
    {
        if (empty($headerData['purchase_id'])) {
            throw new BusinessException('Purchase reference is required for a purchase return.');
        }

        $purchase = Purchase::query()->find($headerData['purchase_id']);
        if (! $purchase || $purchase->status !== PurchaseStatusEnum::CONFIRMED) {
            throw new BusinessException('Purchase returns can only be created against confirmed purchases.');
        }

        if (empty($items)) {
            throw new BusinessException('Purchase return must contain at least one item.');
        }

        $refundType = $headerData['refund_type'] ?? null;
        if ($refundType instanceof PurchaseReturnRefundType) {
            $refundType = $refundType->value;
        }
        if ($refundType && $refundType !== PurchaseReturnRefundType::CREDIT_NOTE->value && empty($headerData['refund_account_id'])) {
            throw new BusinessException('A valid refund account is required for cash or bank purchase returns.');
        }

        foreach ($items as $index => $item) {
            $this->validateLineItem($purchase, $item, $index, $excludeReturnId);
        }
    }

    public function validateLineItem(Purchase $purchase, array $item, int $index, ?int $excludeReturnId = null): void
    {
        $returnedQty = (float) ($item['returned_quantity'] ?? 0);
        if ($returnedQty <= 0) {
            throw new BusinessException("Item at line #{$index} must have a returned quantity greater than zero.");
        }

        if (empty($item['purchase_item_id'])) {
            throw new BusinessException("Item at line #{$index} must reference an original purchase item.");
        }

        /** @var PurchaseItem|null $purchaseItem */
        $purchaseItem = PurchaseItem::query()->find($item['purchase_item_id']);
        if (! $purchaseItem || $purchaseItem->purchase_id !== $purchase->id) {
            throw new BusinessException("Item at line #{$index} does not belong to the referenced purchase.");
        }

        if ((int) $item['product_id'] !== (int) $purchaseItem->product_id) {
            throw new BusinessException("Product at line #{$index} does not match original purchase item.");
        }

        if (! empty($item['product_variant_id']) && (int) $item['product_variant_id'] !== (int) $purchaseItem->product_variant_id) {
            throw new BusinessException("Product variant at line #{$index} does not match original purchase item.");
        }

        $priorConfirmedQty = (float) PurchaseReturnItem::query()
            ->where('purchase_item_id', $purchaseItem->id)
            ->whereHas('purchaseReturn', function ($q) use ($excludeReturnId) {
                $q->where('status', PurchaseReturnStatus::CONFIRMED);
                if ($excludeReturnId !== null) {
                    $q->where('id', '!=', $excludeReturnId);
                }
            })->sum('returned_quantity');

        $quantityToValidate = $aggregateReturnedQty ?? $returnedQty;
        $availableQty = (float) $purchaseItem->quantity - $priorConfirmedQty;
        if ($quantityToValidate > $availableQty + 0.0001) {
            throw ValidationException::withMessages([
                "items.{$index}.returned_quantity" => [
                    "Returned quantity ({$quantityToValidate}) exceeds available return quantity ({$availableQty}).",
                ],
            ]);
        }

        $product = Product::query()->find($item['product_id']);
        if ($product) {
            $this->validateTrackedItems($product, $item);
        }
    }

    public function validateTrackedItems(Product $product, array $item): void
    {
        if (! $product->requiresStock()) {
            return;
        }

        $lineQty = (float) ($item['returned_quantity'] ?? 0);

        if ($product->isBatchTracked()) {
            $batches = $item['batches'] ?? [];
            if (empty($batches)) {
                throw new BusinessException("Product [{$product->name}] requires batch details for return.");
            }

            $batchQtySum = 0.0;
            foreach ($batches as $batch) {
                if (empty($batch['batch_no']) || ($batch['quantity'] ?? 0) <= 0) {
                    throw new BusinessException("Valid batch number and quantity are required.");
                }
                $batchQtySum += (float) $batch['quantity'];

                if (! empty($item['warehouse_id'])) {
                    $batchModel = ProductBatch::query()
                        ->where('batch_no', $batch['batch_no'])
                        ->where('product_id', $product->id)
                        ->where('warehouse_id', $item['warehouse_id'])
                        ->first();
                    if ($batchModel && (float) $batchModel->remaining_quantity < (float) $batch['quantity']) {
                        throw new BusinessException("Batch [{$batch['batch_no']}] has insufficient stock.");
                    }
                }
            }

            if (abs($batchQtySum - $lineQty) > 0.0001) {
                throw new BusinessException("Batch quantities sum [{$batchQtySum}] does not match line return quantity [{$lineQty}].");
            }
        }

        if ($product->isSerialTracked()) {
            $serials = $item['serials'] ?? [];
            if (empty($serials)) {
                throw new BusinessException("Product [{$product->name}] requires serial numbers for return.");
            }

            $serialNumbers = [];
            foreach ($serials as $serial) {
                $serialNo = trim(is_array($serial) ? ($serial['serial_number'] ?? $serial['serial_no'] ?? '') : (string) $serial);
                if ($serialNo === '' || in_array($serialNo, $serialNumbers, true)) {
                    throw new BusinessException("Duplicate or empty serial number [{$serialNo}].");
                }
                $serialNumbers[] = $serialNo;

                $serialModel = ProductSerial::query()
                    ->where('serial_number', $serialNo)
                    ->where('product_id', $product->id)
                    ->first();
                if (! $serialModel || $serialModel->status !== ProductSerialStatusEnum::AVAILABLE) {
                    throw new BusinessException("Serial [{$serialNo}] is unavailable or not found.");
                }
            }

            if (count($serialNumbers) !== (int) $lineQty) {
                throw new BusinessException("Serials count does not match line return quantity [{$lineQty}].");
            }
        }
    }
}
