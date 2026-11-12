<?php

namespace App\Modules\GoodsReceiptNote\Services\Inventory;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use App\Modules\Product\Enums\InventoryTrackingTypeEnum;

use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNote;
use App\Modules\GoodsReceiptNote\Enums\GoodsReceiptNoteStatusEnum;
use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNoteItem;

use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Models\PurchaseItem;
use App\Modules\Purchase\Enums\PurchaseStatusEnum;

use App\Modules\Inventory\Data\InventoryReversalData;
use App\Modules\Inventory\Enums\InventoryTransactionTypeEnum;
use App\Modules\Inventory\Models\ProductSerial;
use App\Modules\Inventory\Models\StockLedger;

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
     * Post only the quantities approved by this Purchase.
     *
     * Stock ledger references point to Purchase so each partial bill
     * reconciles to its own inventory and accounting value. GRN batch
     * and serial metadata remains the traceability source.
     */
    public function post(
        GoodsReceiptNote $goodsReceiptNote,
        Purchase $purchase,
        string $currencyCode = 'INR',
        string|float $exchangeRate = '1.00000000',
        string $baseCurrency = 'INR',
    ): Collection {
        return DB::transaction(function () use (
            $goodsReceiptNote,
            $purchase,
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

            if (
                $grn->status !== GoodsReceiptNoteStatusEnum::CONFIRMED
                || (int) $purchase->goods_receipt_note_id !== (int) $grn->id
            ) {
                throw ValidationException::withMessages([
                    'goods_receipt_note' => [
                        'A confirmed GRN linked to this Purchase is required for inventory posting.',
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
                        'A valid Purchase currency and positive exchange rate are required.',
                    ],
                ]);
            }

            if (
                $currencyCode === $baseCurrency
                && bccomp($exchangeRate, '1.00000000', 8) !== 0
            ) {
                throw ValidationException::withMessages([
                    'exchange_rate' => [
                        'Exchange rate must be 1 when Purchase currency matches company base currency.',
                    ],
                ]);
            }

            $existingPurchaseLedgers = $this->stockLedgerRepository
                ->findByReference(
                    Purchase::class,
                    (int) $purchase->getKey(),
                    InventoryTransactionTypeEnum::PURCHASE->value,
                );

            if ($existingPurchaseLedgers->isNotEmpty()) {
                $activeLedgers = $existingPurchaseLedgers
                    ->filter(fn ($ledger) => ! $ledger->reversal()->exists())
                    ->values();

                if ($activeLedgers->count() === $existingPurchaseLedgers->count()) {
                    return $activeLedgers;
                }

                throw ValidationException::withMessages([
                    'inventory' => [
                        'This Purchase has an incomplete or reversed inventory posting and requires reconciliation.',
                    ],
                ]);
            }

            $legacyGrnLedgers = $this->stockLedgerRepository
                ->findByReference(
                    GoodsReceiptNote::class,
                    (int) $grn->getKey(),
                    InventoryTransactionTypeEnum::PURCHASE->value,
                );

            if ($legacyGrnLedgers->contains(
                fn ($ledger) => ! $ledger->reversal()->exists()
            )) {
                throw ValidationException::withMessages([
                    'inventory' => [
                        'Legacy GRN inventory posting exists. Reverse or reconcile it before confirming a linked Purchase.',
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
                        'All Purchase bills for one GRN must use the same currency and exchange rate.',
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

            $purchase->loadMissing('items');

            if ($purchase->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => [
                        'A GRN-linked Purchase must contain at least one item.',
                    ],
                ]);
            }

            $confirmedStatuses = [
                PurchaseStatusEnum::CONFIRMED->value,
                PurchaseStatusEnum::PARTIALLY_PAID->value,
                PurchaseStatusEnum::PAID->value,
            ];

            $currentGrnItemQuantities = [];
            $currentBatchQuantities = [];
            $ledgers = new Collection();

            foreach ($purchase->items as $purchaseItem) {
                $grnItem = $grn->items->firstWhere(
                    'id',
                    $purchaseItem->goods_receipt_note_item_id
                );

                if (! $grnItem) {
                    throw ValidationException::withMessages([
                        'items' => [
                            'Every Purchase item must reference an item from its linked GRN.',
                        ],
                    ]);
                }

                $this->validator->validate($grnItem);

                $product = $grnItem->product;

                $purchaseQuantity = (float) $purchaseItem->base_quantity;

                if ($purchaseQuantity <= 0) {
                    throw ValidationException::withMessages([
                        'items' => [
                            'Purchase base quantity must be greater than zero.',
                        ],
                    ]);
                }

                $previousPurchaseItems = PurchaseItem::query()
                    ->where('goods_receipt_note_item_id', $grnItem->id)
                    ->where('purchase_id', '!=', $purchase->id)
                    ->whereHas('purchase', fn ($query) =>
                        $query->whereIn('status', $confirmedStatuses)
                    );

                $previousPurchaseIds = (clone $previousPurchaseItems)
                    ->pluck('purchase_id');

                $previousBilledQuantity = (float) $previousPurchaseItems
                    ->sum('base_quantity');

                $alreadyBilledQuantity = $previousBilledQuantity
                    + (float) ($currentGrnItemQuantities[$grnItem->id] ?? 0);

                if (
                    $alreadyBilledQuantity + $purchaseQuantity
                    > (float) $grnItem->base_quantity + 0.0001
                ) {
                    throw ValidationException::withMessages([
                        'items' => [
                            'Confirmed Purchase quantities exceed the received GRN quantity.',
                        ],
                    ]);
                }

                if (! $product || ! $product->requiresStock()) {
                    $currentGrnItemQuantities[$grnItem->id] =
                        $alreadyBilledQuantity - $previousBilledQuantity
                        + $purchaseQuantity;

                    continue;
                }

                $baseUnitCost = (float) bcmul(
                    (string) $purchaseItem->base_unit_cost,
                    $exchangeRate,
                    8,
                );

                if (
                    $product->inventory_tracking_type
                    === InventoryTrackingTypeEnum::BATCH
                ) {
                    $remainingQuantity = $purchaseQuantity;

                    foreach ($grnItem->batches as $grnBatch) {
                        $batch = $this->batchService->resolve(
                            $grn,
                            $grnItem,
                            $grnBatch,
                            $baseUnitCost,
                        );

                        $previousBatchQuantity = (float) StockLedger::query()
                            ->where('referenceable_type', Purchase::class)
                            ->whereIn('reference_id', $previousPurchaseIds)
                            ->where('product_batch_id', $batch->id)
                            ->where('transaction_type', InventoryTransactionTypeEnum::PURCHASE->value)
                            ->sum('quantity_in');

                        $alreadyPostedBatchQuantity = $previousBatchQuantity
                            + (float) ($currentBatchQuantities[$batch->id] ?? 0);

                        $availableBatchQuantity = max(
                            0,
                            (float) $grnBatch->quantity - $alreadyPostedBatchQuantity
                        );

                        $allocatedQuantity = min(
                            $availableBatchQuantity,
                            $remainingQuantity
                        );

                        if ($allocatedQuantity <= 0.0001) {
                            continue;
                        }

                        $movement = $this->movementFactory->make(
                            purchase: $purchase,
                            grn: $grn,
                            item: $grnItem,
                            quantity: $allocatedQuantity,
                            unitCost: $baseUnitCost,
                            batchId: (int) $batch->getKey(),
                        );

                        $ledgers->push(
                            $this->inventoryPostingService->stockIn($movement)
                        );

                        $currentBatchQuantities[$batch->id] =
                            $alreadyPostedBatchQuantity + $allocatedQuantity;

                        $remainingQuantity -= $allocatedQuantity;

                        if ($remainingQuantity <= 0.0001) {
                            break;
                        }
                    }

                    if ($remainingQuantity > 0.0001) {
                        throw ValidationException::withMessages([
                            'batches' => [
                                'GRN batch quantities are insufficient for this Purchase quantity.',
                            ],
                        ]);
                    }
                } elseif (
                    $product->inventory_tracking_type
                    === InventoryTrackingTypeEnum::SERIAL
                ) {
                    $wholeQuantity = (int) round($purchaseQuantity);

                    if (abs($purchaseQuantity - $wholeQuantity) > 0.0001) {
                        throw ValidationException::withMessages([
                            'items' => [
                                'Serial-tracked Purchase quantities must be whole base units.',
                            ],
                        ]);
                    }

                    $remainingSerials = $wholeQuantity;

                    foreach ($grnItem->serials as $grnSerial) {
                        if ($remainingSerials <= 0) {
                            break;
                        }

                        $serialNumber = trim(
                            (string) $grnSerial->serial_number
                        );

                        $existingSerial = $this->inventorySerialService
                            ->findBySerialNumber($serialNumber);

                        if ($existingSerial !== null) {
                            if (
                                $existingSerial->sourceable_type !== GoodsReceiptNote::class
                                || (int) $existingSerial->sourceable_id !== (int) $grn->id
                            ) {
                                throw ValidationException::withMessages([
                                    'serials' => [
                                        "Serial [{$serialNumber}] is already registered by another source.",
                                    ],
                                ]);
                            }

                            continue;
                        }

                        $serial = $this->inventorySerialService->registerDraft(
                            productId: (int) $grnItem->product_id,
                            warehouseId: (int) $grnItem->warehouse_id,
                            serialNumber: $serialNumber,
                            purchaseCost: $baseUnitCost,
                            productVariantId: $grnItem->product_variant_id
                                ? (int) $grnItem->product_variant_id
                                : null,
                            productBatchId: null,
                            imeiNumber: null,
                            warrantyExpiry: null,
                            sourceableType: GoodsReceiptNote::class,
                            sourceableId: (int) $grn->id,
                            remarks: 'GRN ' . $grn->grn_number,
                        );

                        $this->inventorySerialService->activate($serial);

                        $movement = $this->movementFactory->make(
                            purchase: $purchase,
                            grn: $grn,
                            item: $grnItem,
                            quantity: 1,
                            unitCost: $baseUnitCost,
                            serialId: (int) $serial->getKey(),
                        );

                        $ledgers->push(
                            $this->inventoryPostingService->stockIn($movement)
                        );

                        $remainingSerials--;
                    }

                    if ($remainingSerials > 0) {
                        throw ValidationException::withMessages([
                            'serials' => [
                                'GRN serial numbers are insufficient for this Purchase quantity.',
                            ],
                        ]);
                    }
                } else {
                    $movement = $this->movementFactory->make(
                        purchase: $purchase,
                        grn: $grn,
                        item: $grnItem,
                        quantity: $purchaseQuantity,
                        unitCost: $baseUnitCost,
                    );

                    $ledgers->push(
                        $this->inventoryPostingService->stockIn($movement)
                    );
                }

                $currentGrnItemQuantities[$grnItem->id] =
                    $alreadyBilledQuantity - $previousBilledQuantity
                    + $purchaseQuantity;
            }

            $grn->update([
                'inventory_currency_code' => $currencyCode,
                'inventory_exchange_rate' => $exchangeRate,
            ]);

            return $ledgers;
        });
    }

    /**
     * Detect legacy inventory entries posted directly by GRN confirmation.
     * New Purchase-linked movements reference the Purchase document.
     */
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

    /**
     * Reverse legacy PURCHASE stock-ledger entries that reference this GRN.
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
                fn (GoodsReceiptNoteItem $item) =>
                    $item->product !== null
                    && $item->product->requiresStock()
            );

            if ($originalLedgers->isEmpty()) {
                if (! $hasStockItems) {
                    return;
                }

                throw ValidationException::withMessages([
                    'inventory' => [
                        'No original legacy GRN inventory entries were found to reverse.',
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
                        remarks: 'Legacy GRN inventory reversal ' . $grn->grn_number,
                    )
                );
            }
        });
    }
}
