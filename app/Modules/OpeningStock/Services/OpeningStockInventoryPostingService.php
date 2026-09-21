<?php

namespace App\Modules\OpeningStock\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

use App\Core\Exceptions\BusinessException;
use App\Core\Enums\DocumentStatusEnum;

use App\Modules\OpeningStock\Models\OpeningStock;
use App\Modules\OpeningStock\Models\OpeningStockItem;
use App\Modules\OpeningStock\Models\OpeningStockSource;

use App\Modules\Inventory\Data\InventoryMovementData;
use App\Modules\Inventory\Data\InventoryReversalData;

use App\Modules\Inventory\Enums\InventoryTransactionTypeEnum;

use App\Modules\Inventory\Services\InventoryPostingService;
use App\Modules\Inventory\Services\InventoryReversalService;
use App\Modules\Inventory\Services\InventoryStockInService;
use App\Modules\Inventory\Services\InventoryBatchService;
use App\Modules\Inventory\Services\InventorySerialService;

use App\Modules\Inventory\Repositories\Contracts\StockLedgerRepositoryInterface;

use App\Modules\Accounting\Enums\JournalVoucherTypeEnum;
use App\Modules\Accounting\Services\AccountingPostingService;
use App\Modules\Accounting\Services\AccountingReversalService;

class OpeningStockInventoryPostingService
{
    public function __construct(
        protected InventoryPostingService $inventoryPostingService,
        protected InventoryStockInService $inventoryStockInService,
        protected InventoryReversalService $inventoryReversalService,
        protected InventoryBatchService $batchService,
        protected InventorySerialService $serialService,
        protected StockLedgerRepositoryInterface $stockLedgerRepository,
        protected AccountingPostingService $accountingPostingService,
        protected AccountingReversalService $accountingReversalService,
    ) {}

    public function post(OpeningStock $openingStock): void
    {
        DB::transaction(function () use ($openingStock) {

            $this->ensureDraft($openingStock);

            $openingStock->loadMissing([
                'sources.items',
            ]);

            if (! $this->hasItems($openingStock)) {
                throw new BusinessException(
                    'Opening stock has no items.'
                );
            }

            foreach ($openingStock->sources as $source) {
                foreach ($source->items as $item) {

                    $movement = new InventoryMovementData(
                        productId: $item->product_id,
                        productVariantId: $item->product_variant_id,
                        warehouseId: $openingStock->warehouse_id,
                        quantity: (float) $item->quantity,
                        unitCost: (float) $item->unit_cost,
                        transactionType:
                            InventoryTransactionTypeEnum::OPENING_STOCK->value,
                        transactionDate: $openingStock->opening_date,
                        referenceType: OpeningStock::class,
                        referenceId: $openingStock->id,
                        referenceNo: $openingStock->document_no,
                        batchId: $item->product_batch_id,
                        serialId: $item->product_serial_id,
                        remarks: $item->remarks,
                    );

                    /*
                     * Validate the movement before activating
                     * batch/serial inventory.
                     *
                     * This is important for duplicate serials:
                     * the validator must detect an existing stock-in
                     * before activate() changes the serial lifecycle.
                     */
                    $this->inventoryStockInService->validate(
                        $movement
                    );

                    $this->activateInventory(
                        $item
                    );

                    $this->inventoryPostingService->stockIn(
                        $movement
                    );

                    $this->initializeBatchOriginalQuantity(
                        $item
                    );
                }
            }

            $this->accountingPostingService->postOpeningStock(
                $openingStock
            );

            $openingStock->update([
                'status' => DocumentStatusEnum::CONFIRMED->value,
            ]);
        });
    }

    public function unpost(OpeningStock $openingStock): void
    {
        DB::transaction(function () use ($openingStock) {

            $this->ensureConfirmed($openingStock);

            $ledgers = $this->stockLedgerRepository->findByReference(
                referenceType: OpeningStock::class,
                referenceId: $openingStock->id,
                transactionType:
                    InventoryTransactionTypeEnum::OPENING_STOCK->value,
            );

            if ($ledgers->isEmpty()) {
                throw new BusinessException(
                    'Opening stock inventory ledger not found.'
                );
            }

            $openingStock->loadMissing([
                'sources.items',
            ]);

            $this->validateUnpostSafety(
                $ledgers
            );

            foreach ($ledgers as $ledger) {
                $reversal = new InventoryReversalData(
                    stockLedgerId: $ledger->id,
                    reversalDate: $openingStock->opening_date,
                    referenceType: OpeningStock::class,
                    referenceId: $openingStock->id,
                    referenceNo: $openingStock->document_no,
                    remarks:
                        'Reversal of opening stock '
                        . $openingStock->document_no,
                );

                $this->inventoryReversalService->reverse(
                    $reversal
                );
            }

            $this->restoreInventory(
                $ledgers
            );

            $this->accountingReversalService->reverseByReference(
                referenceType: OpeningStock::class,
                referenceId: $openingStock->id,
                voucherType:
                    JournalVoucherTypeEnum::OPENING_STOCK->value,
            );

            $openingStock->update([
                'status' => DocumentStatusEnum::DRAFT->value,
            ]);
        });
    }

    /*
     * TODO:
     * Review original_quantity initialization when
     * Purchase/GRN inventory posting is implemented.
     */
    protected function initializeBatchOriginalQuantity(
        OpeningStockItem $item
    ): void {
        if (! $item->product_batch_id) {
            return;
        }

        $batch = $this->batchService->lock(
            $item->product_batch_id
        );

        $this->batchService->initializeOriginalQuantity(
            $batch,
            (float) $item->quantity
        );
    }

    protected function activateInventory(
        OpeningStockItem $item
    ): void {
        if ($item->product_batch_id) {
            $batch = $this->batchService->lock(
                $item->product_batch_id
            );

            $this->batchService->activate(
                $batch
            );
        }

        if ($item->product_serial_id) {
            $serial = $this->serialService->lock(
                $item->product_serial_id
            );

            $this->serialService->activate(
                $serial
            );
        }
    }

    protected function restoreInventory(
        Collection $ledgers
    ): void {
        foreach ($ledgers as $ledger) {

            if ($ledger->product_batch_id) {
                $batch = $this->batchService->lock(
                    $ledger->product_batch_id
                );

                $this->batchService->restoreDraft(
                    $batch
                );
            }

            if ($ledger->product_serial_id) {
                $serial = $this->serialService->lock(
                    $ledger->product_serial_id
                );

                $this->serialService->restoreDraft(
                    $serial
                );
            }
        }
    }

    protected function validateUnpostSafety(
        Collection $ledgers
    ): void {
        foreach ($ledgers as $ledger) {

            if (
                $ledger->product_batch_id
                && $this->stockLedgerRepository
                    ->hasOtherMovementForBatch(
                        batchId: $ledger->product_batch_id,
                        openingLedgerId: $ledger->id,
                    )
            ) {
                throw new BusinessException(
                    'Opening stock batch has subsequent inventory movements.'
                );
            }

            if (
                $ledger->product_serial_id
                && $this->stockLedgerRepository
                    ->hasOtherMovementForSerial(
                        serialId: $ledger->product_serial_id,
                        openingLedgerId: $ledger->id,
                    )
            ) {
                throw new BusinessException(
                    'Opening stock serial has subsequent inventory movements.'
                );
            }
        }
    }

    protected function hasItems(
        OpeningStock $openingStock
    ): bool {
        return $openingStock->sources->contains(
            fn (OpeningStockSource $source): bool =>
                $source->items->isNotEmpty()
        );
    }

    protected function ensureDraft(
        OpeningStock $openingStock
    ): void {
        if (
            $openingStock->status
            !== DocumentStatusEnum::DRAFT->value
        ) {
            throw new BusinessException(
                'Only draft opening stock can be posted.'
            );
        }
    }

    protected function ensureConfirmed(
        OpeningStock $openingStock
    ): void {
        if (
            $openingStock->status
            !== DocumentStatusEnum::CONFIRMED->value
        ) {
            throw new BusinessException(
                'Only confirmed opening stock can be unposted.'
            );
        }
    }
}