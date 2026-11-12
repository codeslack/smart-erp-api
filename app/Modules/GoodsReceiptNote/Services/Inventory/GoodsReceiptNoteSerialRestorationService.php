<?php

namespace App\Modules\GoodsReceiptNote\Services\Inventory;

use Illuminate\Support\Facades\DB;

use App\Core\Exceptions\BusinessException;

use App\Modules\Inventory\Enums\ProductSerialStatusEnum;
use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNote;

use App\Modules\Inventory\Services\InventorySerialService;
use App\Modules\Inventory\Enums\InventoryTransactionTypeEnum;
use App\Modules\Inventory\Repositories\Contracts\StockLedgerRepositoryInterface;

class GoodsReceiptNoteSerialRestorationService
{
    public function __construct(
        protected InventorySerialService $serialService,
        protected StockLedgerRepositoryInterface $stockLedgers,
    ) {}

    /**
     * Restore serials registered by this GRN after inventory reversal.
     *
     * Must run inside the GRN cancellation database transaction.
     */
    public function restoreFor(
        GoodsReceiptNote $goodsReceiptNote
    ): void {
        if (! DB::transactionLevel()) {
            throw new BusinessException(
                'Serial restoration must run inside a database transaction.'
            );
        }

        $originalLedgers = $this->stockLedgers->findByReference(
            GoodsReceiptNote::class,
            (int) $goodsReceiptNote->getKey(),
            InventoryTransactionTypeEnum::PURCHASE->value,
        );

        // GRN confirmation no longer registers inventory serials. If no
        // Purchase has posted this GRN, there is nothing to restore.
        if ($originalLedgers->isEmpty()) {
            return;
        }

        $goodsReceiptNote->loadMissing('items.serials');

        foreach ($goodsReceiptNote->items as $item) {
            foreach ($item->serials as $grnSerial) {
                $serialNumber = trim(
                    (string) $grnSerial->serial_number
                );

                if ($serialNumber === '') {
                    throw new BusinessException(
                        'GRN serial number cannot be empty.'
                    );
                }

                $serial = $this->serialService
                    ->findBySerialNumber($serialNumber);

                if (! $serial) {
                    throw new BusinessException(
                        "Inventory serial [{$serialNumber}] was not found."
                    );
                }

                /*
                 * Lock the serial row before validating its current state.
                 */
                $serial = $this->serialService->lock(
                    (int) $serial->getKey()
                );

                /*
                 * Ensure the serial was registered by this exact GRN.
                 */
                if (
                    $serial->sourceable_type !== GoodsReceiptNote::class
                    || (int) $serial->sourceable_id
                        !== (int) $goodsReceiptNote->getKey()
                ) {
                    throw new BusinessException(
                        "Serial [{$serialNumber}] does not belong to this goods receipt note."
                    );
                }

                /*
                 * A draft serial is already in the desired state.
                 */
                if (
                    $serial->status
                    === ProductSerialStatusEnum::DRAFT
                ) {
                    continue;
                }

                /*
                 * Only available serials can be restored to draft.
                 */
                if (
                    $serial->status
                    !== ProductSerialStatusEnum::AVAILABLE
                ) {
                    throw new BusinessException(
                        "Serial [{$serialNumber}] cannot be restored because it is not available."
                    );
                }

                $this->serialService->restoreDraft($serial);
            }
        }
    }
}