<?php

namespace App\Modules\GoodsReceiptNote\Services\Inventory;

use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNote;
use App\Modules\Inventory\Services\InventoryBatchService;

class GoodsReceiptNoteBatchRestorationService
{
    public function __construct(
        protected InventoryBatchService $batchService,
    ) {}

    /**
     * Restore batches originally created by this GRN.
     *
     * Existing batches reused by the GRN are excluded because their
     * sourceable reference belongs to another source document.
     */
    public function restoreFor(
        GoodsReceiptNote $grn
    ): void {
        $batches = $this->batchService->findBySourceForUpdate(
            GoodsReceiptNote::class,
            (int) $grn->getKey(),
        );

        foreach ($batches as $batch) {
            $this->batchService->restoreDraft($batch);
        }
    }
}