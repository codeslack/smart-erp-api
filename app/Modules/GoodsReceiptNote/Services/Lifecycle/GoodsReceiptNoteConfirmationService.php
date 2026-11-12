<?php

namespace App\Modules\GoodsReceiptNote\Services\Lifecycle;

use Illuminate\Support\Facades\DB;

use App\Core\Exceptions\BusinessException;

use App\Modules\GoodsReceiptNote\Rules\GoodsReceiptNoteRules;

use App\Modules\GoodsReceiptNote\Enums\GoodsReceiptNoteStatusEnum;
use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNote;

use App\Modules\GoodsReceiptNote\Repositories\Contracts\GoodsReceiptNoteItemRepositoryInterface;
use App\Modules\GoodsReceiptNote\Repositories\Contracts\GoodsReceiptNoteRepositoryInterface;

use App\Modules\PurchaseOrder\Services\PurchaseOrderReceivingService;
use App\Modules\PurchaseOrder\Services\PurchaseOrderStatusService;

class GoodsReceiptNoteConfirmationService
{
    public function __construct(
        protected GoodsReceiptNoteRepositoryInterface $repository,
        protected GoodsReceiptNoteItemRepositoryInterface $items,
        protected GoodsReceiptNoteRules $rules,
        protected PurchaseOrderReceivingService $receivingService,
        protected PurchaseOrderStatusService $statusService,
    ) {}

    public function confirm(
        GoodsReceiptNote $goodsReceiptNote
    ): GoodsReceiptNote {
        return DB::transaction(function () use ($goodsReceiptNote) {
            /*
             * Reload and lock the latest GRN header before
             * validating or changing its lifecycle state.
             */
            $grn = $this->repository->findWithItemsByUuidForUpdate(
                $goodsReceiptNote->uuid
            );

            if (! $grn) {
                throw new BusinessException(
                    'Goods receipt note not found.'
                );
            }

            $this->rules->ensureCanConfirm($grn);

            $this->validateItems($grn);

            $purchaseOrder = $grn->purchaseOrder;

            if (! $purchaseOrder) {
                throw new BusinessException(
                    'Purchase order not found for this goods receipt note.'
                );
            }

            /*
             * Lock each distinct PO item in a consistent order.
             * This helps serialize concurrent receipts against
             * the same PO items and reduces deadlock risk.
             */
            $lockedItems = [];

            $sortedItems = $grn->items
                ->sortBy('purchase_order_item_id')
                ->values();

            foreach ($sortedItems as $grnItem) {
                if (! $grnItem->purchase_order_item_id) {
                    throw new BusinessException(
                        'Purchase order item is required for every GRN item.'
                    );
                }

                $purchaseOrderItemId =
                    (int) $grnItem->purchase_order_item_id;

                if (isset($lockedItems[$purchaseOrderItemId])) {
                    continue;
                }

                $lockedItems[$purchaseOrderItemId] =
                    $this->receivingService->lockPurchaseOrderItem(
                        $purchaseOrder,
                        $purchaseOrderItemId
                    );
            }

            /*
             * Cache the confirmed quantity for each PO item.
             * Add each processed GRN line to this running total
             * so duplicate lines cannot bypass quantity validation.
             */
            $receivedBaseQuantitiesInThisGrn = [];

            foreach ($sortedItems as $grnItem) {
                $purchaseOrderItemId =
                    (int) $grnItem->purchase_order_item_id;

                if (! isset($lockedItems[$purchaseOrderItemId])) {
                    throw new BusinessException(
                        'Purchase order item could not be locked.'
                    );
                }

                $purchaseOrderItem =
                    $lockedItems[$purchaseOrderItemId];

                if (! array_key_exists(
                    $purchaseOrderItemId,
                    $receivedBaseQuantitiesInThisGrn
                )) {
                    $receivedBaseQuantitiesInThisGrn[
                        $purchaseOrderItemId
                    ] = (float) $this->items
                        ->getReceivedQuantityByPurchaseOrderItemId(
                            $purchaseOrderItemId
                        );
                }

                $confirmedReceivedBaseQuantity =
                    $receivedBaseQuantitiesInThisGrn[
                        $purchaseOrderItemId
                    ];

                $receivedBaseQuantity =
                    (float) $grnItem->base_quantity;

                $this->receivingService->receive(
                    purchaseOrder: $purchaseOrder,
                    purchaseOrderItem: $purchaseOrderItem,
                    receivedBaseQuantity: $receivedBaseQuantity,
                    confirmedReceivedBaseQuantity:
                        $confirmedReceivedBaseQuantity,
                );

                /*
                 * Include this line before validating the next
                 * line referencing the same PO item.
                 */
                $receivedBaseQuantitiesInThisGrn[
                    $purchaseOrderItemId
                ] += $receivedBaseQuantity;
            }

            /*
             * Confirm after PO receiving quantities have been validated.
             * Inventory is posted later by Purchase confirmation.
             */
            $this->repository->update($grn, [
                'status' => GoodsReceiptNoteStatusEnum::CONFIRMED->value,
                'confirmed_at' => now(),
                'confirmed_by' => userId(),
                'updated_by' => userId(),
            ]);

            /*
             * Recalculate the Purchase Order status using the
             * updated received quantities.
             */
            $this->statusService->refresh(
                $purchaseOrder->fresh('items')
            );

            return $this->repository->findWithItems($grn->id)
                ?? $grn->refresh();
        });
    }

    protected function validateItems(
        GoodsReceiptNote $grn
    ): void {
        if ($grn->items->isEmpty()) {
            throw new BusinessException(
                'A goods receipt note must contain at least one item.'
            );
        }

        foreach ($grn->items as $item) {
            $this->rules->ensurePositiveQuantity(
                (float) $item->base_quantity
            );

            $this->rules->ensureItemHasTrackingData($item);
        }
    }
}
