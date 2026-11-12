<?php

namespace App\Modules\GoodsReceiptNote\Services\Inventory;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use App\Core\Exceptions\BusinessException;
use App\Modules\Settings\Models\Setting;

use App\Modules\Product\Enums\InventoryTrackingTypeEnum;
use App\Modules\Inventory\Enums\InventoryTransactionTypeEnum;

use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNote;
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
        GoodsReceiptNote $goodsReceiptNote
    ): Collection {
        return DB::transaction(function () use ($goodsReceiptNote) {
            $grn = GoodsReceiptNote::query()
                ->with([
                    'items.product',
                    'items.batches',
                    'items.serials',
                    'purchaseOrder',
                ])
                ->whereKey($goodsReceiptNote->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $existingLedgers = $this->stockLedgerRepository
                ->findByReference(
                    GoodsReceiptNote::class,
                    (int) $grn->getKey(),
                    InventoryTransactionTypeEnum::PURCHASE->value,
                );

            if ($existingLedgers->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'inventory' => [
                        'Inventory has already been posted for this goods receipt note.',
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

            $purchaseOrder = $grn->purchaseOrder;

            if (! $purchaseOrder) {
                throw new BusinessException(
                    'The purchase order for this goods receipt note was not found.'
                );
            }

            $baseCurrency = strtoupper(trim((string) (
                Setting::query()
                    ->where('group', Setting::GROUP_COMPANY)
                    ->where('key', 'currency')
                    ->value('value') ?? 'INR'
            )));
            $currencyCode = strtoupper(trim(
                (string) ($purchaseOrder->currency_code ?: $baseCurrency)
            ));
            $exchangeRate = (string) (
                $purchaseOrder->exchange_rate ?? '1.000000'
            );

            if (
                ! preg_match('/^[A-Z]{3}$/', $baseCurrency)
                || ! preg_match('/^[A-Z]{3}$/', $currencyCode)
                || ! is_numeric($exchangeRate)
                || bccomp($exchangeRate, '0', 8) <= 0
            ) {
                throw new BusinessException(
                    'Purchase order currency or exchange rate is invalid.'
                );
            }

            if (
                $currencyCode === $baseCurrency
                && bccomp($exchangeRate, '1.00000000', 8) !== 0
            ) {
                throw new BusinessException(
                    'Exchange rate must be 1 when purchase order currency matches company base currency.'
                );
            }

            $ledgers = new Collection();

            foreach ($grn->items as $item) {
                $this->validator->validate($item);

                $product = $item->product;

                if (! $product->requiresStock()) {
                    continue;
                }

                // GRN base_unit_cost is normalized to the product's base UOM
                // but remains in the PO transaction currency until converted.
                $baseCost = (float) bcmul(
                    (string) $item->base_unit_cost,
                    $exchangeRate,
                    8
                );

                if (
                    $product->inventory_tracking_type === InventoryTrackingTypeEnum::BATCH
                ) {
                    foreach ($item->batches as $grnBatch) {
                        $batch = $this->batchService->resolve(
                            $grn,
                            $item,
                            $grnBatch,
                            $baseCost,
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

            return $ledgers;
        });
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
                if ($ledger->reversed_by_id !== null) {
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
