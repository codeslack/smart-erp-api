<?php

namespace App\Modules\Purchase\Services\Validators;

use App\Core\Exceptions\BusinessException;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Models\PurchaseItem;
use App\Modules\Purchase\Enums\PurchaseStatusEnum;
use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNote;
use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNoteItem;
use App\Modules\PurchaseOrder\Models\PurchaseOrder;
use App\Modules\PurchaseOrder\Models\PurchaseOrderItem;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductVariant;

class PurchaseValidator
{
    public function __construct(
        protected ?PurchaseBatchSerialValidator $batchSerialValidator = null
    ) {
        $this->batchSerialValidator ??= new PurchaseBatchSerialValidator();
    }

    public function validateDraft(array $data, array $items): void
    {
        $supplierId = (int) ($data['supplier_id'] ?? 0);
        $grnId = isset($data['goods_receipt_note_id']) ? (int) $data['goods_receipt_note_id'] : null;
        $poId = isset($data['purchase_order_id']) ? (int) $data['purchase_order_id'] : null;

        if ($grnId !== null) {
            $grn = GoodsReceiptNote::query()->find($grnId);
            if (! $grn) {
                throw new BusinessException('Referenced Goods Receipt Note not found.');
            }
            if ((int) $grn->supplier_id !== $supplierId) {
                throw new BusinessException('Goods Receipt Note does not belong to the selected supplier.');
            }
            if (! $grn->isConfirmed()) {
                throw new BusinessException('Goods Receipt Note must be confirmed before creating a purchase bill.');
            }
        }

        if ($poId !== null) {
            $po = PurchaseOrder::query()->find($poId);
            if (! $po) {
                throw new BusinessException('Referenced Purchase Order not found.');
            }
            if ((int) $po->supplier_id !== $supplierId) {
                throw new BusinessException('Purchase Order does not belong to the selected supplier.');
            }
        }

        foreach ($items as $item) {
            $this->validateLineItem($item, $grnId, $poId, $supplierId);
        }
    }

    public function validateLineItem(array $item, ?int $headerGrnId = null, ?int $headerPoId = null, ?int $supplierId = null): void
    {
        $productId = (int) ($item['product_id'] ?? 0);
        $variantId = isset($item['product_variant_id']) ? (int) $item['product_variant_id'] : null;
        $grnItemId = isset($item['goods_receipt_note_item_id']) ? (int) $item['goods_receipt_note_item_id'] : null;
        $poItemId = isset($item['purchase_order_item_id']) ? (int) $item['purchase_order_item_id'] : null;

        $product = Product::query()->find($productId);
        if (! $product) {
            throw new BusinessException("Product [ID: {$productId}] not found.");
        }

        if ($variantId !== null) {
            $variant = ProductVariant::query()->find($variantId);
            if (! $variant || (int) $variant->product_id !== $productId) {
                throw new BusinessException("Product Variant does not belong to Product [{$product->name}].");
            }
            if (! $variant->is_active) {
                throw new BusinessException("Product Variant [{$variant->name}] is inactive.");
            }
        }

        if ($grnItemId !== null) {
            $grnItem = GoodsReceiptNoteItem::query()->find($grnItemId);
            if (! $grnItem) {
                throw new BusinessException("GRN item [ID: {$grnItemId}] not found.");
            }
            if ($headerGrnId !== null && (int) $grnItem->goods_receipt_note_id !== $headerGrnId) {
                throw new BusinessException("GRN item does not belong to the selected Goods Receipt Note.");
            }

            $grn = $grnItem->goodsReceiptNote;

            if (! $grn || ! $grn->isConfirmed()) {
                throw new BusinessException(
                    'Goods Receipt Note must be confirmed before creating a purchase bill.'
                );
            }

            if (
                $supplierId !== null
                && (int) $grn->supplier_id !== $supplierId
            ) {
                throw new BusinessException(
                    'GRN item does not belong to the selected supplier.'
                );
            }

            if ((int) $grnItem->product_id !== $productId) {
                throw new BusinessException("Line product does not match GRN item product.");
            }
            if ((int) ($grnItem->product_variant_id ?? 0) !== (int) ($variantId ?? 0)) {
                throw new BusinessException("Line variant does not match GRN item variant.");
            }
        }

        if ($poItemId !== null) {
            $poItem = PurchaseOrderItem::query()->find($poItemId);
            if (! $poItem) {
                throw new BusinessException("PO item [ID: {$poItemId}] not found.");
            }
            if ($headerPoId !== null && (int) $poItem->purchase_order_id !== $headerPoId) {
                throw new BusinessException("PO item does not belong to the selected Purchase Order.");
            }
            if ((int) $poItem->product_id !== $productId) {
                throw new BusinessException("Line product does not match PO item product.");
            }
            if ((int) ($poItem->product_variant_id ?? 0) !== (int) ($variantId ?? 0)) {
                throw new BusinessException("Line variant does not match PO item variant.");
            }
        }

        if (
            $headerGrnId !== null
            && $grnItemId === null
            && $product->requiresStock()
        ) {
            throw new BusinessException(
                'Every stock-tracked Purchase line linked to a GRN must reference its GRN item.'
            );
        }

        if ($headerGrnId === null && $grnItemId === null) {
            $this->batchSerialValidator->validate($product, $item);
        }
    }

    public function validateCumulativeBilledQuantities(Purchase $purchase): void
    {
        foreach ($purchase->items as $item) {
            if ($item->goods_receipt_note_item_id === null) {
                continue;
            }

            /** @var GoodsReceiptNoteItem|null $unlockedGrnItem */
            $unlockedGrnItem = GoodsReceiptNoteItem::query()
                ->find($item->goods_receipt_note_item_id);

            if (! $unlockedGrnItem) {
                throw new BusinessException("GRN item not found for line [{$item->id}].");
            }

            $grn = GoodsReceiptNote::query()
                ->whereKey($unlockedGrnItem->goods_receipt_note_id)
                ->lockForUpdate()
                ->first();

            if (! $grn || ! $grn->isConfirmed()) {
                throw new BusinessException(
                    'Goods Receipt Note must remain confirmed until the linked Purchase is approved.'
                );
            }

            /** @var GoodsReceiptNoteItem|null $grnItem */
            $grnItem = GoodsReceiptNoteItem::query()
                ->whereKey($unlockedGrnItem->getKey())
                ->lockForUpdate()
                ->first();

            $alreadyBilled = (float) PurchaseItem::query()
                ->where('goods_receipt_note_item_id', $grnItem->id)
                ->where('id', '!=', $item->id)
                ->whereHas('purchase', fn ($q) => $q->where('status', PurchaseStatusEnum::CONFIRMED->value))
                ->get(['quantity', 'base_quantity'])
                ->sum(fn (PurchaseItem $billedItem) =>
                    (float) ($billedItem->base_quantity ?? $billedItem->quantity)
                );

            $maxAllowed = (float) (
                $grnItem->base_quantity
                ?? $grnItem->quantity_received
            );

            $newTotal = $alreadyBilled + (float) (
                $item->base_quantity
                ?? $item->quantity
            );

            if ($newTotal > ($maxAllowed + 0.0001)) {
                $available = max(0, $maxAllowed - $alreadyBilled);
                throw new BusinessException(
                    "Cumulative billed quantity [{$newTotal}] exceeds received quantity [{$maxAllowed}] for GRN item [{$grnItem->id}]. Remaining billable: {$available}."
                );
            }
        }
    }
}
