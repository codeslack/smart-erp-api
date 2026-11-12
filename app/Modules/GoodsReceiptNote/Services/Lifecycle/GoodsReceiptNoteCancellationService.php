<?php

namespace App\Modules\GoodsReceiptNote\Services\Lifecycle;

use Illuminate\Support\Facades\DB;

use App\Core\Exceptions\BusinessException;

use App\Modules\GoodsReceiptNote\Enums\GoodsReceiptNoteStatusEnum;
use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNote;
use App\Modules\GoodsReceiptNote\Repositories\Contracts\GoodsReceiptNoteRepositoryInterface;
use App\Modules\GoodsReceiptNote\Rules\GoodsReceiptNoteRules;

use App\Modules\ProductUnit\Models\ProductUnit;
use App\Modules\ProductUnit\Services\ProductUnitService;

use App\Modules\PurchaseOrder\Models\PurchaseOrderItem;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Enums\PurchaseStatusEnum;
use App\Modules\PurchaseOrder\Repositories\Contracts\PurchaseOrderItemRepositoryInterface;

use App\Modules\GoodsReceiptNote\Services\Inventory\GoodsReceiptNoteInventoryPostingService;
use App\Modules\GoodsReceiptNote\Services\Inventory\GoodsReceiptNoteBatchRestorationService;
use App\Modules\GoodsReceiptNote\Services\Inventory\GoodsReceiptNoteSerialRestorationService;

use App\Modules\PurchaseOrder\Services\PurchaseOrderReceivingService;
use App\Modules\PurchaseOrder\Services\PurchaseOrderStatusService;

class GoodsReceiptNoteCancellationService
{
    public function __construct(
        protected GoodsReceiptNoteRepositoryInterface $repository,
        protected PurchaseOrderItemRepositoryInterface $items,
        protected GoodsReceiptNoteRules $rules,
        protected ProductUnitService $productUnits,
        protected PurchaseOrderReceivingService $receivingService,
        protected PurchaseOrderStatusService $statusService,
        protected GoodsReceiptNoteInventoryPostingService $inventoryPostingService,
        protected GoodsReceiptNoteBatchRestorationService $batchRestorationService,
        protected GoodsReceiptNoteSerialRestorationService $serialRestorationService,
    ) {}

    public function cancel(
        GoodsReceiptNote $goodsReceiptNote
    ): GoodsReceiptNote {
        return DB::transaction(function () use ($goodsReceiptNote) {
            /*
             * Reload and lock the latest GRN header.
             */
            $grn = $this->repository->findWithItemsByUuidForUpdate(
                $goodsReceiptNote->uuid
            );

            if (! $grn) {
                throw new BusinessException(
                    'Goods receipt note not found.'
                );
            }

            $this->rules->ensureCanCancel($grn);

            $purchaseOrder = $grn->purchaseOrder;

            if (! $purchaseOrder) {
                throw new BusinessException(
                    'Purchase order not found for this goods receipt note.'
                );
            }

            $grnItems = $grn->items
                ->sortBy('purchase_order_item_id')
                ->values();

            $grnItemIds = $grnItems->pluck('id');

            $hasConfirmedPurchase = Purchase::query()
                ->where(function ($query) use ($grn, $grnItemIds) {
                    $query->where('goods_receipt_note_id', $grn->id)
                        ->orWhereHas('items', fn ($items) =>
                            $items->whereIn('goods_receipt_note_item_id', $grnItemIds)
                        );
                })
                ->whereNotIn('status', [
                    PurchaseStatusEnum::DRAFT->value,
                    PurchaseStatusEnum::CANCELLED->value,
                ])
                ->exists();

            if ($hasConfirmedPurchase) {
                throw new BusinessException(
                    'A goods receipt note cannot be cancelled after a linked Purchase has been confirmed.'
                );
            }

            if ($grnItems->isEmpty()) {
                throw new BusinessException(
                    'Goods receipt note must contain at least one item.'
                );
            }

            /*
             * Lock each distinct PO item in a consistent order.
             */
            $lockedItems = [];

            foreach ($grnItems as $grnItem) {
                if (! $grnItem->purchase_order_item_id) {
                    throw new BusinessException(
                        'Purchase order item is required for every GRN item.'
                    );
                }

                $itemId = (int) $grnItem->purchase_order_item_id;

                if (isset($lockedItems[$itemId])) {
                    continue;
                }

                $lockedItems[$itemId] =
                    $this->receivingService->lockPurchaseOrderItem(
                        $purchaseOrder,
                        $itemId
                    );
            }

            /*
             * Validate every deduction before reversing inventory.
             * This prevents repeated GRN lines from independently
             * passing against the same original PO received quantity.
             */
            $deductionsByItem = [];

            foreach ($grnItems as $grnItem) {
                $itemId = (int) $grnItem->purchase_order_item_id;

                if (! isset($lockedItems[$itemId])) {
                    throw new BusinessException(
                        'Purchase order item could not be locked.'
                    );
                }

                $purchaseOrderItem = $lockedItems[$itemId];

                $baseQuantity = (float) $grnItem->base_quantity;

                if (! is_finite($baseQuantity) || $baseQuantity <= 0) {
                    throw new BusinessException(
                        'GRN base quantity must be greater than zero.'
                    );
                }

                $productUnit = $this->getProductUnit(
                    $purchaseOrderItem
                );

                $receivedPoQuantity =
                    (float) $this->productUnits->fromBaseQuantity(
                        $productUnit,
                        $baseQuantity
                    );

                if (
                    ! is_finite($receivedPoQuantity) ||
                    $receivedPoQuantity <= 0
                ) {
                    throw new BusinessException(
                        'Converted purchase order quantity must be greater than zero.'
                    );
                }

                $deductionsByItem[$itemId] =
                    ($deductionsByItem[$itemId] ?? 0.0)
                    + $receivedPoQuantity;
            }

            /*
             * Check the aggregate deduction against each locked
             * PO item's current received quantity.
             */
            foreach ($deductionsByItem as $itemId => $deduction) {
                $purchaseOrderItem = $lockedItems[$itemId];

                $currentReceivedQuantity =
                    (float) $purchaseOrderItem->received_quantity;

                if (
                    $deduction >
                    $currentReceivedQuantity + 0.0001
                ) {
                    throw new BusinessException(
                        'GRN cancellation would make purchase order received quantity negative.'
                    );
                }
            }

            /*
             * Legacy GRNs may already have inventory movements from
             * the previous posting boundary. Reverse only when such
             * movements exist; new GRNs are posted by Purchase approval.
             */
            if ($this->inventoryPostingService->hasPosting($grn)) {
                $this->inventoryPostingService->reverse($grn);
                $this->batchRestorationService->restoreFor($grn);
                $this->serialRestorationService->restoreFor($grn);
            }

            /*
             * Apply one aggregate deduction per PO item.
             */
            foreach ($deductionsByItem as $itemId => $deduction) {
                $this->items->decrementReceivedQuantity(
                    $lockedItems[$itemId]->id,
                    (string) $deduction
                );
            }

            /*
             * Mark the GRN cancelled only after the reversals
             * and PO quantity deductions have succeeded.
             */
            $grn->status =
                GoodsReceiptNoteStatusEnum::CANCELLED->value;

            $grn->cancelled_at = now();
            $grn->cancelled_by = userId();
            $grn->updated_by = userId();

            $grn->save();

            /*
             * Recalculate the PO lifecycle status.
             */
            $this->statusService->refresh(
                $purchaseOrder->fresh('items')
            );

            return $this->repository->findWithItems($grn->id)
                ?? $grn->refresh();
        });
    }

    protected function getProductUnit(
        PurchaseOrderItem $item
    ): ProductUnit {
        if (
            ! $item->product_id ||
            ! $item->unit_of_measure_id
        ) {
            throw new BusinessException(
                'Product and unit are required for purchase order cancellation.'
            );
        }

        $productUnit =
            $this->productUnits->findByProductAndUnit(
                (int) $item->product_id,
                (int) $item->unit_of_measure_id
            );

        if (! $productUnit) {
            throw new BusinessException(
                'The purchase order unit is not configured for this product.'
            );
        }

        $this->productUnits->ensurePurchaseUnit($productUnit);

        return $productUnit;
    }
}