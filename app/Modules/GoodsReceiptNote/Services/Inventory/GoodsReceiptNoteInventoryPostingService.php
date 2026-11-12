<?php

namespace App\Modules\GoodsReceiptNote\Services\Inventory;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use App\Modules\Product\Enums\InventoryTrackingTypeEnum;
use App\Modules\Inventory\Enums\InventoryTransactionTypeEnum;

use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNote;
use App\Modules\GoodsReceiptNote\Enums\GoodsReceiptNoteStatusEnum;
use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNoteItem;

use App\Modules\Inventory\Data\InventoryReversalData;

use App\Modules\Inventory\Services\InventoryPostingService;
use App\Modules\Inventory\Services\InventoryReversalService;
use App\Modules\Inventory\Services\InventorySerialService;

use App\Modules\Inventory\Repositories\Contracts\StockLedgerRepositoryInterface;

class GoodsReceiptNoteInventoryPostingService
{
    public function __construct(
        protected InventoryPostingService $inventoryPostingService,
        protected InventoryReversalService $inventoryReversalService,
        protected InventorySerialService $inventorySerialService,
        protected StockLedgerRepositoryInterface $stockLedgerRepository,
        protected GoodsReceiptNoteInventoryValidator $validator,
        protected GoodsReceiptNoteInventoryMovementFactory $movementFactory,
        protected GoodsReceiptNoteInventoryBatchService $batchService,
    ) {}

    /**
     * Post inventory movements for a confirmed GRN.
     */
    public function post(
        GoodsReceiptNote $goodsReceiptNote,
        string $currencyCode = 'INR',
        string|float $exchangeRate = '1.00000000',
        string $baseCurrency = 'INR',
    ): Collection {
        return DB::transaction(function () use (
            $goodsReceiptNote,
            $currencyCode,
            $exchangeRate,
            $baseCurrency,
        ) {
            $grn = GoodsReceiptNote::query()
                ->with([
                    'items.product',
                    'items.batches',
                    'items.serials',
                ])
                ->whereKey($goodsReceiptNote->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($grn->status !== GoodsReceiptNoteStatusEnum::CONFIRMED) {
                throw ValidationException::withMessages([
                    'status' => [
                        'Inventory can only be posted for a confirmed goods receipt note.',
                    ],
                ]);
            }

            $baseCurrency = strtoupper(trim($baseCurrency));
            $currencyCode = strtoupper(trim($currencyCode ?: $baseCurrency));
            $exchangeRate = bcadd((string) $exchangeRate, '0', 8);

            if (
                ! preg_match('/^[A-Z]{3}$/', $currencyCode)
                || $baseCurrency === ''
                || bccomp($exchangeRate, '0.00000000', 8) <= 0
            ) {
                throw ValidationException::withMessages([
                    'currency' => [
                        'A valid GRN inventory currency and positive exchange rate are required.',
                    ],
                ]);
            }

            if (
                $currencyCode === $baseCurrency
                && bccomp($exchangeRate, '1.00000000', 8) !== 0
            ) {
                throw ValidationException::withMessages([
                    'exchange_rate' => [
                        'Exchange rate must be 1 when the inventory currency matches company base currency.',
                    ],
                ]);
            }

            $storedCurrency = strtoupper(
                (string) ($grn->inventory_currency_code ?? $baseCurrency)
            );
            $storedRate = (string) (
                $grn->inventory_exchange_rate ?? '1.00000000'
            );

            if (
                ($grn->inventory_currency_code !== null || $grn->inventory_exchange_rate !== null)
                && (
                    $storedCurrency !== $currencyCode
                    || bccomp($storedRate, $exchangeRate, 8) !== 0
                )
            ) {
                throw ValidationException::withMessages([
                    'currency' => [
                        'A GRN inventory valuation currency and exchange rate cannot change after its first Purchase approval.',
                    ],
                ]);
            }

            $existingLedgers = $this->stockLedgerRepository
                ->findByReference(
                    GoodsReceiptNote::class,
                    (int) $grn->getKey(),
                    InventoryTransactionTypeEnum::PURCHASE->value,
                );

            if ($existingLedgers->isNotEmpty()) {
                $activeLedgers = $existingLedgers
                    ->filter(fn ($ledger) => ! $ledger->reversal()->exists())
                    ->values();

                $expectedCount = $this->expectedLedgerCount($grn);

                if (
                    $expectedCount > 0
                    && $activeLedgers->count() === $expectedCount
                    && $existingLedgers->count() === $expectedCount
                ) {
                    if (
                        $grn->inventory_currency_code === null
                        && $grn->inventory_exchange_rate === null
                    ) {
                        if (
                            $currencyCode !== $baseCurrency
                            || bccomp($exchangeRate, '1.00000000', 8) !== 0
                        ) {
                            throw ValidationException::withMessages([
                                'currency' => [
                                    'Legacy GRN inventory was valued in base currency and cannot be revalued by a later Purchase.',
                                ],
                            ]);
                        }

                        $grn->update([
                            'inventory_currency_code' => $baseCurrency,
                            'inventory_exchange_rate' => '1.00000000',
                        ]);
                    }

                    return $activeLedgers;
                }

                throw ValidationException::withMessages([
                    'inventory' => [
                        'The goods receipt note has an incomplete or reversed inventory posting and requires reconciliation.',
                    ],
                ]);
            }

            if ($grn->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => [
                        'A goods receipt note must contain at least one item.',
                    ],
                ]);
            }

            $ledgers = new Collection();

            foreach ($grn->items as $item) {
                $this->validator->validate($item);

                $product = $item->product;

                if (! $product->requiresStock()) {
                    continue;
                }

                $baseCost = (float) bcmul(
                    (string) $item->base_unit_cost,
                    $exchangeRate,
                    8,
                );

                if (
                    $product->inventory_tracking_type === InventoryTrackingTypeEnum::BATCH
                ) {
                    foreach ($item->batches as $grnBatch) {
                        $batch = $this->batchService->resolve(
                            $grn,
                            $item,
                            $grnBatch,
                        );

                        $movement = $this->movementFactory->make(
                            $grn,
                            $item,
                            (float) $grnBatch->quantity,
                            $baseCost,
                            batchId: (int) $batch->getKey(),
                        );

                        $posted = $this->inventoryPostingService
                            ->stockIn($movement);

                        $ledgers->push($posted);
                    }

                    continue;
                }

                if (
                    $product->inventory_tracking_type === InventoryTrackingTypeEnum::SERIAL
                ) {
                    foreach ($item->serials as $grnSerial) {
                        $serialNumber = trim(
                            (string) $grnSerial->serial_number
                        );

                        $serial = $this->inventorySerialService
                            ->registerDraft(
                                productId: (int) $item->product_id,
                                warehouseId: (int) $item->warehouse_id,
                                serialNumber: $serialNumber,
                                purchaseCost: $baseCost,
                                productVariantId: $item->product_variant_id
                                    ? (int) $item->product_variant_id
                                    : null,
                                productBatchId: null,
                                imeiNumber: null,
                                warrantyExpiry: null,
                                sourceableType: GoodsReceiptNote::class,
                                sourceableId: (int) $grn->getKey(),
                                remarks: 'GRN ' . $grn->grn_number,
                            );

                        $this->inventorySerialService->activate($serial);

                        $movement = $this->movementFactory->make(
                            $grn,
                            $item,
                            1.0,
                            $baseCost,
                            serialId: (int) $serial->getKey(),
                        );

                        $posted = $this->inventoryPostingService
                            ->stockIn($movement);

                        $ledgers->push($posted);
                    }

                    continue;
                }

                $movement = $this->movementFactory->make(
                    $grn,
                    $item,
                    (float) $item->base_quantity,
                    $baseCost,
                );

                $posted = $this->inventoryPostingService
                    ->stockIn($movement);

                $ledgers->push($posted);
            }

            $grn->update([
                'inventory_currency_code' => $currencyCode,
                'inventory_exchange_rate' => $exchangeRate,
            ]);

            return $ledgers;
        });
    }

    public function hasActivePosting(
        GoodsReceiptNote $goodsReceiptNote
    ): bool {
        return $this->stockLedgerRepository
            ->findByReference(
                GoodsReceiptNote::class,
                (int) $goodsReceiptNote->getKey(),
                InventoryTransactionTypeEnum::PURCHASE->value,
            )
            ->contains(fn ($ledger) => ! $ledger->reversal()->exists());
    }

    protected function expectedLedgerCount(
        GoodsReceiptNote $grn
    ): int {
        $count = 0;

        foreach ($grn->items as $item) {
            $product = $item->product;

            if (! $product || ! $product->requiresStock()) {
                continue;
            }

            if ($product->isBatchTracked()) {
                $count += $item->batches->count();
                continue;
            }

            if ($product->isSerialTracked()) {
                $count += $item->serials->count();
                continue;
            }

            $count++;
        }

        return $count;
    }

    /**
     * Reverse original PURCHASE stock-ledger entries for this GRN.
     *
     * Does not manually decrement stock. InventoryReversalService
     * is responsible for reversing each original inventory movement.
     */
    public function reverse(
        GoodsReceiptNote $goodsReceiptNote
    ): void {
        DB::transaction(function () use ($goodsReceiptNote) {
            $grn = GoodsReceiptNote::query()
                ->with(['items.product'])
                ->whereKey($goodsReceiptNote->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $originalLedgers = $this->stockLedgerRepository
                ->findByReference(
                    GoodsReceiptNote::class,
                    (int) $grn->getKey(),
                    InventoryTransactionTypeEnum::PURCHASE->value,
                );

            $hasStockItems = $grn->items->contains(
                fn(GoodsReceiptNoteItem $item) =>
                $item->product !== null
                    && $item->product->requiresStock()
            );

            if ($originalLedgers->isEmpty()) {
                if (! $hasStockItems) {
                    return;
                }

                throw ValidationException::withMessages([
                    'inventory' => [
                        'No original inventory entries were found to reverse.',
                    ],
                ]);
            }

            foreach ($originalLedgers as $ledger) {
                if ($ledger->reversal()->exists()) {
                    continue;
                }

                $this->inventoryReversalService->reverse(
                    new InventoryReversalData(
                        stockLedgerId: (int) $ledger->getKey(),
                        reversalDate: now(),
                        referenceType: GoodsReceiptNote::class,
                        referenceId: (int) $grn->getKey(),
                        referenceNo: $grn->grn_number,
                        remarks: 'Reversal for GRN ' . $grn->grn_number,
                    )
                );
            }
        });
    }
}
