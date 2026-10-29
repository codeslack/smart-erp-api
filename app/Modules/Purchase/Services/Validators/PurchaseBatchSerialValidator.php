<?php

namespace App\Modules\Purchase\Services\Validators;

use App\Core\Exceptions\BusinessException;
use App\Modules\Product\Models\Product;

class PurchaseBatchSerialValidator
{
    public function validate(Product $product, array $item): void
    {
        if (! $product->requiresStock()) {
            return;
        }

        $lineQty = (float) ($item['quantity'] ?? 0);

        if ($product->isBatchTracked()) {
            $batches = $item['batches'] ?? [];
            if (empty($batches)) {
                throw new BusinessException("Product [{$product->name}] requires batch details.");
            }

            $batchQtySum = 0;
            foreach ($batches as $batch) {
                if (empty($batch['batch_no'])) {
                    throw new BusinessException("Batch number is required for product [{$product->name}].");
                }
                if (($batch['quantity'] ?? 0) <= 0) {
                    throw new BusinessException("Batch quantity must be greater than zero.");
                }
                if ($product->hasExpiryTracking() && empty($batch['expiry_date'])) {
                    throw new BusinessException("Expiry date is required for batch [{$batch['batch_no']}].");
                }
                $batchQtySum += (float) $batch['quantity'];
            }

            if (abs($batchQtySum - $lineQty) > 0.0001) {
                throw new BusinessException(
                    "Sum of batch quantities [{$batchQtySum}] does not match line quantity [{$lineQty}]."
                );
            }
        }

        if ($product->isSerialTracked()) {
            $serials = $item['serials'] ?? [];
            if (empty($serials)) {
                throw new BusinessException("Product [{$product->name}] requires serial numbers.");
            }

            $serialNumbers = [];
            foreach ($serials as $serial) {
                $serialNo = is_array($serial) ? ($serial['serial_number'] ?? $serial['serial_no'] ?? '') : (string) $serial;
                $serialNo = trim($serialNo);
                if ($serialNo === '') {
                    throw new BusinessException("Serial number cannot be empty.");
                }
                if (in_array($serialNo, $serialNumbers, true)) {
                    throw new BusinessException("Duplicate serial number [{$serialNo}] in purchase line.");
                }
                $serialNumbers[] = $serialNo;
            }

            if (count($serialNumbers) !== (int) $lineQty) {
                throw new BusinessException(
                    "Number of serials [" . count($serialNumbers) . "] must equal line quantity [{$lineQty}]."
                );
            }
        }
    }
}

