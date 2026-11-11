<?php

namespace App\Modules\SalesReturn\Services\Validators;

use App\Modules\Sales\Enums\SaleStatusEnum;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use App\Modules\SalesReturn\Enums\SalesReturnStatus;
use App\Modules\SalesReturn\Models\SaleReturnItemBatch;
use App\Modules\SalesReturn\Models\SaleReturnItemSerial;
use App\Modules\SalesReturn\Models\SalesReturn;
use App\Modules\SalesReturn\Models\SalesReturnItem;
use Illuminate\Validation\ValidationException;

class SalesReturnValidator
{
    public function validateForSave(
        array $header,
        array $items,
        ?int $ignoreReturnId = null
    ): void {
        if (empty($items)) {
            throw ValidationException::withMessages([
                'items' => ['Sales return must have at least one line item.'],
            ]);
        }

        $saleId = (int) ($header['sale_id'] ?? 0);
        $sale = $saleId > 0 ? Sale::query()->lockForUpdate()->find($saleId) : null;

        $saleStatus = $sale?->status instanceof SaleStatusEnum
            ? $sale->status
            : SaleStatusEnum::tryFrom((string) ($sale?->status ?? ''));

        if (
            ! $sale
            || ! in_array($saleStatus, [
                SaleStatusEnum::CONFIRMED,
                SaleStatusEnum::PARTIALLY_PAID,
                SaleStatusEnum::PAID,
            ], true)
        ) {
            throw ValidationException::withMessages([
                'sale_id' => ['Sales return must reference a confirmed sale.'],
            ]);
        }

        $seenSaleItemIds = [];

        foreach ($items as $item) {
            $quantity = (float) (
                $item['returned_quantity']
                ?? $item['quantity']
                ?? 0
            );

            if ($quantity <= 0) {
                throw ValidationException::withMessages([
                    'items' => ['Returned quantity must be greater than zero.'],
                ]);
            }

            $saleItemId = (int) ($item['sale_item_id'] ?? 0);

            if ($saleItemId <= 0) {
                throw ValidationException::withMessages([
                    'items' => ['Every sales return line must reference an original sale item.'],
                ]);
            }

            if (isset($seenSaleItemIds[$saleItemId])) {
                throw ValidationException::withMessages([
                    'items' => ['An original sale item may appear only once in a sales return.'],
                ]);
            }

            $seenSaleItemIds[$saleItemId] = true;

            /** @var SaleItem|null $saleItem */
            $saleItem = SaleItem::query()
                ->whereKey($saleItemId)
                ->where('sale_id', $saleId)
                ->with(['product', 'batches', 'serials'])
                ->lockForUpdate()
                ->first();

            if (! $saleItem) {
                throw ValidationException::withMessages([
                    'items' => ['The selected sale item does not belong to the referenced sale.'],
                ]);
            }

            $soldQuantity = (float) $saleItem->quantity;

            $alreadyReturned = (float) SalesReturnItem::query()
                ->where('sale_item_id', $saleItem->id)
                ->whereHas('salesReturn', function ($query) use ($ignoreReturnId) {
                    $query->whereIn('status', [
                        SalesReturnStatus::DRAFT->value,
                        SalesReturnStatus::CONFIRMED->value,
                    ]);

                    if ($ignoreReturnId) {
                        $query->where('id', '!=', $ignoreReturnId);
                    }
                })
                ->sum('returned_quantity');

            $maxReturnable = max(0, $soldQuantity - $alreadyReturned);

            if ($quantity > $maxReturnable) {
                throw ValidationException::withMessages([
                    'items' => [
                        "Returned quantity ({$quantity}) exceeds available returnable quantity ({$maxReturnable}) for item: {$saleItem->item_name}.",
                    ],
                ]);
            }

            $product = $saleItem->product;

            if ($product?->isBatchTracked()) {
                if (! empty($item['serials'])) {
                    throw ValidationException::withMessages([
                        'items' => ['Serial allocations are not valid for batch-tracked products.'],
                    ]);
                }

                $this->validateBatchReturns(
                    $saleItem,
                    $item,
                    $quantity,
                    $ignoreReturnId
                );

                continue;
            }

            if ($product?->isSerialTracked()) {
                if (! empty($item['batches'])) {
                    throw ValidationException::withMessages([
                        'items' => ['Batch allocations are not valid for serial-tracked products.'],
                    ]);
                }

                $this->validateSerialReturns(
                    $saleItem,
                    $item,
                    $quantity,
                    $ignoreReturnId
                );

                continue;
            }

            if (! empty($item['batches']) || ! empty($item['serials'])) {
                throw ValidationException::withMessages([
                    'items' => ['Batch or serial allocations are not valid for this product.'],
                ]);
            }
        }
    }

    protected function validateBatchReturns(
        SaleItem $saleItem,
        array $item,
        float $returnQuantity,
        ?int $ignoreReturnId
    ): void {
        $batches = $item['batches'] ?? [];

        if (empty($batches)) {
            throw ValidationException::withMessages([
                'items' => ["Batch details are required for {$saleItem->item_name}."],
            ]);
        }

        $soldQuantity = (float) $saleItem->quantity;
        $soldBaseQuantity = (float) (
            $saleItem->base_quantity
            ?? $saleItem->quantity
        );
        $expectedBaseQuantity = $soldQuantity > 0
            ? $soldBaseQuantity * ($returnQuantity / $soldQuantity)
            : 0.0;

        $quantityTotal = 0.0;
        $baseQuantityTotal = 0.0;
        $seenBatchNumbers = [];

        foreach ($batches as $batch) {
            $batchNo = (string) ($batch['batch_no'] ?? '');
            $batchQuantity = (float) ($batch['quantity'] ?? 0);

            if ($batchNo === '' || $batchQuantity <= 0) {
                throw ValidationException::withMessages([
                    'items' => ['Every returned batch requires a batch number and positive quantity.'],
                ]);
            }

            if (isset($seenBatchNumbers[$batchNo])) {
                throw ValidationException::withMessages([
                    'items' => ["Batch [{$batchNo}] may only appear once per return line."],
                ]);
            }

            $seenBatchNumbers[$batchNo] = true;

            $sourceBatch = $saleItem->batches
                ->firstWhere('batch_no', $batchNo);

            if (! $sourceBatch) {
                throw ValidationException::withMessages([
                    'items' => ["Batch [{$batchNo}] was not sold on this sale item."],
                ]);
            }

            $alreadyReturnedBatchQuantity = (float) SaleReturnItemBatch::query()
                ->where('batch_no', $batchNo)
                ->whereHas('salesReturnItem', fn ($query) =>
                    $query->where('sale_item_id', $saleItem->id)
                )
                ->whereHas('salesReturn', function ($query) use ($ignoreReturnId) {
                    $query->whereIn('status', [
                        SalesReturnStatus::DRAFT->value,
                        SalesReturnStatus::CONFIRMED->value,
                    ]);

                    if ($ignoreReturnId) {
                        $query->where('id', '!=', $ignoreReturnId);
                    }
                })
                ->sum('quantity');

            $availableBatchQuantity = max(
                0,
                (float) $sourceBatch->quantity - $alreadyReturnedBatchQuantity
            );

            if ($batchQuantity > $availableBatchQuantity) {
                throw ValidationException::withMessages([
                    'items' => [
                        "Returned batch quantity for [{$batchNo}] exceeds the quantity sold from that batch.",
                    ],
                ]);
            }

            $quantityTotal += $batchQuantity;
            $baseQuantityTotal += (float) (
                $batch['base_quantity']
                ?? $batchQuantity
            );
        }

        if (abs($quantityTotal - $returnQuantity) > 0.0001) {
            throw ValidationException::withMessages([
                'items' => ['Returned batch quantities must equal the return line quantity.'],
            ]);
        }

        if (abs($baseQuantityTotal - $expectedBaseQuantity) > 0.0001) {
            throw ValidationException::withMessages([
                'items' => ['Returned batch base quantities must equal the return line base quantity.'],
            ]);
        }
    }

    protected function validateSerialReturns(
        SaleItem $saleItem,
        array $item,
        float $returnQuantity,
        ?int $ignoreReturnId
    ): void {
        $serials = $item['serials'] ?? [];

        if (empty($serials)) {
            throw ValidationException::withMessages([
                'items' => ["Serial numbers are required for {$saleItem->item_name}."],
            ]);
        }

        $soldQuantity = (float) $saleItem->quantity;
        $soldBaseQuantity = (float) (
            $saleItem->base_quantity
            ?? $saleItem->quantity
        );
        $expectedBaseQuantity = $soldQuantity > 0
            ? $soldBaseQuantity * ($returnQuantity / $soldQuantity)
            : 0.0;

        if (
            abs($expectedBaseQuantity - round($expectedBaseQuantity)) > 0.0001
            || count($serials) !== (int) round($expectedBaseQuantity)
        ) {
            throw ValidationException::withMessages([
                'items' => ['Returned serial count must equal the return line base quantity.'],
            ]);
        }

        $seenSerialNumbers = [];

        foreach ($serials as $serial) {
            $serialNumber = is_array($serial)
                ? (string) ($serial['serial_number'] ?? '')
                : (string) $serial;

            if ($serialNumber === '' || isset($seenSerialNumbers[$serialNumber])) {
                throw ValidationException::withMessages([
                    'items' => ['Returned serial numbers must be present and unique.'],
                ]);
            }

            $seenSerialNumbers[$serialNumber] = true;

            $sourceSerial = $saleItem->serials
                ->firstWhere('serial_number', $serialNumber);

            if (! $sourceSerial || ! $sourceSerial->product_serial_id) {
                throw ValidationException::withMessages([
                    'items' => ["Serial [{$serialNumber}] was not sold on this sale item."],
                ]);
            }

            $alreadyReturned = SaleReturnItemSerial::query()
                ->where('product_serial_id', $sourceSerial->product_serial_id)
                ->whereHas('salesReturn', function ($query) use ($ignoreReturnId) {
                    $query->whereIn('status', [
                        SalesReturnStatus::DRAFT->value,
                        SalesReturnStatus::CONFIRMED->value,
                    ]);

                    if ($ignoreReturnId) {
                        $query->where('id', '!=', $ignoreReturnId);
                    }
                })
                ->exists();

            if ($alreadyReturned) {
                throw ValidationException::withMessages([
                    'items' => ["Serial [{$serialNumber}] has already been returned."],
                ]);
            }
        }
    }

    public function validateForUpdate(SalesReturn $return): void
    {
        $status = $return->status instanceof SalesReturnStatus
            ? $return->status
            : SalesReturnStatus::tryFrom((string) $return->status);

        if ($status !== SalesReturnStatus::DRAFT) {
            throw ValidationException::withMessages([
                'status' => ['Only draft sales returns can be updated.'],
            ]);
        }
    }

    public function validateForConfirmation(SalesReturn $return): void
    {
        $status = $return->status instanceof SalesReturnStatus
            ? $return->status
            : SalesReturnStatus::tryFrom((string) $return->status);

        if ($status !== SalesReturnStatus::DRAFT) {
            throw ValidationException::withMessages([
                'status' => ['Only draft sales returns can be confirmed.'],
            ]);
        }

        if ($return->items()->count() === 0) {
            throw ValidationException::withMessages([
                'items' => ['Cannot confirm a sales return without items.'],
            ]);
        }

        $return->loadMissing([
            'items.batches',
            'items.serials',
        ]);

        $items = $return->items->map(fn (SalesReturnItem $item) => [
            'sale_item_id' => $item->sale_item_id,
            'returned_quantity' => (float) $item->returned_quantity,
            'batches' => $item->batches->map(fn ($batch) => [
                'batch_no' => $batch->batch_no,
                'quantity' => (float) $batch->quantity,
                'base_quantity' => (float) (
                    $batch->base_quantity
                    ?? $batch->quantity
                ),
            ])->all(),
            'serials' => $item->serials->map(fn ($serial) => [
                'serial_number' => $serial->serial_number,
            ])->all(),
        ])->all();

        // Recheck source sale, quantities, batches and serials at confirmation time.
        $this->validateForSave(
            ['sale_id' => $return->sale_id],
            $items,
            $return->id
        );
    }

    public function validateForCancellation(SalesReturn $return): void
    {
        $status = $return->status instanceof SalesReturnStatus
            ? $return->status
            : SalesReturnStatus::tryFrom((string) $return->status);

        if (! in_array($status, [SalesReturnStatus::DRAFT, SalesReturnStatus::CONFIRMED], true)) {
            throw ValidationException::withMessages([
                'status' => ['Cannot cancel a sales return in ' . ($status?->label() ?? 'current') . ' status.'],
            ]);
        }
    }

    public function validateForDeletion(SalesReturn $return): void
    {
        $status = $return->status instanceof SalesReturnStatus
            ? $return->status
            : SalesReturnStatus::tryFrom((string) $return->status);

        if ($status !== SalesReturnStatus::DRAFT) {
            throw ValidationException::withMessages([
                'status' => ['Only draft sales returns can be deleted.'],
            ]);
        }
    }
}
