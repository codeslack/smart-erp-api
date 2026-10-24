<?php

namespace App\Modules\GoodsReceiptNote\Services;

use Illuminate\Support\Facades\DB;

use App\Core\Exceptions\BusinessException;
use App\Core\Services\BaseService;

use App\Modules\DocumentNumber\Enums\DocumentType;

use App\Modules\PurchaseOrder\Enums\PurchaseOrderStatusEnum;
use App\Modules\PurchaseOrder\Models\PurchaseOrder;
use App\Modules\PurchaseOrder\Repositories\Contracts\PurchaseOrderRepositoryInterface;

use App\Modules\GoodsReceiptNote\Rules\GoodsReceiptNoteRules;
use App\Modules\GoodsReceiptNote\Enums\GoodsReceiptNoteStatusEnum;
use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNote;
use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNoteItem;

use App\Modules\ProductUnit\Services\ProductUnitService;

use App\Modules\GoodsReceiptNote\Services\Lifecycle\GoodsReceiptNoteConfirmationService;
use App\Modules\GoodsReceiptNote\Services\Lifecycle\GoodsReceiptNoteCancellationService;

use App\Modules\Product\Repositories\Contracts\ProductRepositoryInterface;
use App\Modules\Product\Repositories\Contracts\ProductVariantRepositoryInterface;
use App\Modules\ProductUnit\Repositories\Contracts\ProductUnitRepositoryInterface;

use App\Modules\GoodsReceiptNote\Repositories\Contracts\GoodsReceiptNoteRepositoryInterface;
use App\Modules\GoodsReceiptNote\Repositories\Contracts\GoodsReceiptNoteItemRepositoryInterface;

class GoodsReceiptNoteService extends BaseService
{
    public function __construct(
        protected GoodsReceiptNoteRepositoryInterface $repository,
        protected GoodsReceiptNoteItemRepositoryInterface $items,
        protected ProductRepositoryInterface $products,
        protected ProductUnitRepositoryInterface $productUnits,
        protected ProductVariantRepositoryInterface $productVariants,
        protected ProductUnitService $productUnitService,
        protected GoodsReceiptNoteItemBatchService $batchService,
        protected GoodsReceiptNoteItemSerialService $serialService,
        protected GoodsReceiptNoteRules $rules,
        protected GoodsReceiptNoteConfirmationService $confirmationService,
        protected GoodsReceiptNoteCancellationService $cancellationService,
        protected PurchaseOrderRepositoryInterface $purchaseOrders,
    ) {}

    public function create(array $data): GoodsReceiptNote
    {
        return DB::transaction(function () use ($data) {
            $items = $data['items'] ?? [];

            if (empty($items)) {
                throw new BusinessException(
                    'A goods receipt note must contain at least one item.'
                );
            }

            $this->validateHeader($data);

            $purchaseOrder = $this->purchaseOrders->findById(
                (int) $data['purchase_order_id']
            );

            if (! $purchaseOrder) {
                throw new BusinessException(
                    'Purchase order not found.'
                );
            }

            // $this->ensurePurchaseOrderReceivable($purchaseOrder);

            $warehouseId = (int) (
                $data['warehouse_id']
                ?? $purchaseOrder->warehouse_id
                ?? 0
            );

            if ($warehouseId <= 0) {
                throw new BusinessException(
                    'A receiving warehouse is required.'
                );
            }

            // The supplier always comes from the selected PO.
            $grn = $this->repository->create([
                'purchase_order_id' => (int) $purchaseOrder->id,
                'supplier_id' => (int) $purchaseOrder->supplier_id,
                'warehouse_id' => $warehouseId,

                'grn_number' => nextDocumentNumber(
                    DocumentType::GOODS_RECEIPT_NOTE
                ),

                'grn_date' => $data['grn_date'],

                'status' => GoodsReceiptNoteStatusEnum::DRAFT->value,

                'delivery_challan_number' =>
                    $data['delivery_challan_number'] ?? null,

                'delivery_challan_date' =>
                    $data['delivery_challan_date'] ?? null,

                'vehicle_number' =>
                    $data['vehicle_number'] ?? null,

                'transporter_name' =>
                    $data['transporter_name'] ?? null,

                'remarks' => $data['remarks'] ?? null,

                'total_received_valuation' => 0,

                'created_by' => userId(),
                'updated_by' => userId(),
            ]);

            foreach ($items as $itemData) {
                $this->createItem($grn, $itemData);
            }

            $this->refreshTotalValuation($grn);

            return $this->repository->findWithItems(
                $grn->id
            ) ?? $grn;
        });
    }

    public function update(
        GoodsReceiptNote $grn,
        array $data
    ): GoodsReceiptNote {
        return DB::transaction(function () use ($grn, $data) {
            $this->rules->ensureDraft($grn);

            // Items must be managed through dedicated item operations.
            if (array_key_exists('items', $data)) {
                throw new BusinessException(
                    'GRN items cannot be updated through the header update endpoint.'
                );
            }

            // These relationships are fixed after GRN creation.
            unset(
                $data['grn_number'],
                $data['status'],
                $data['purchase_order_id'],
                $data['supplier_id'],
                $data['warehouse_id'],
                $data['confirmed_at'],
                $data['confirmed_by'],
                $data['cancelled_at'],
                $data['cancelled_by']
            );

            $data['updated_by'] = userId();

            $this->repository->update($grn, $data);

            return $this->repository->findWithItems(
                $grn->id
            ) ?? $grn->refresh();
        });
    }

    public function createItem(
        GoodsReceiptNote $grn,
        array $data
    ): GoodsReceiptNoteItem {
        return DB::transaction(function () use ($grn, $data) {
            $this->rules->ensureDraft($grn);

            $purchaseOrder = $this->purchaseOrders->findById(
                (int) $grn->purchase_order_id
            );

            if (! $purchaseOrder) {
                throw new BusinessException(
                    'Purchase order not found.'
                );
            }

            // $this->ensurePurchaseOrderReceivable($purchaseOrder);

            $purchaseOrderItemId = (int) (
                $data['purchase_order_item_id'] ?? 0
            );

            if ($purchaseOrderItemId <= 0) {
                throw new BusinessException(
                    'Purchase order item is required.'
                );
            }

            // Retrieve the line through the selected PO relationship
            // so an item from another PO cannot be received here.
            $purchaseOrderItem = $purchaseOrder->items()
                ->whereKey($purchaseOrderItemId)
                ->first();

            if (! $purchaseOrderItem) {
                throw new BusinessException(
                    'The selected item does not belong to this purchase order.'
                );
            }

            $productId = (int) ($data['product_id'] ?? 0);
            $unitId = (int) ($data['unit_id'] ?? 0);

            if ($productId <= 0 || $unitId <= 0) {
                throw new BusinessException(
                    'Product and unit are required for a GRN item.'
                );
            }

            if ((int) $purchaseOrderItem->product_id !== $productId) {
                throw new BusinessException(
                    'The selected product does not match the purchase order item.'
                );
            }

            $variantId = isset($data['product_variant_id'])
                ? (int) $data['product_variant_id']
                : null;

            $poVariantId = $purchaseOrderItem->product_variant_id
                ? (int) $purchaseOrderItem->product_variant_id
                : null;

            if ($variantId !== $poVariantId) {
                throw new BusinessException(
                    'The selected product variant does not match the purchase order item.'
                );
            }

            $product = $this->products->findById($productId);

            if (! $product) {
                throw new BusinessException('Product not found.');
            }

            if ($variantId !== null) {
                $variant = $this->productVariants->findById($variantId);

                if (! $variant) {
                    throw new BusinessException(
                        'Product variant not found.'
                    );
                }

                if ((int) $variant->product_id !== $productId) {
                    throw new BusinessException(
                        'The selected product variant does not belong to the selected product.',
                        'GOODS_RECEIPT_NOTE_INVALID_PRODUCT_VARIANT'
                    );
                }

                if (! $variant->is_active) {
                    throw new BusinessException(
                        'The selected product variant is inactive.',
                        'GOODS_RECEIPT_NOTE_INACTIVE_PRODUCT_VARIANT'
                    );
                }
            }

            // Both the PO unit and GRN unit must be configured for
            // this product. Different units are allowed when their
            // product-unit conversions are configured.
            $poUnitId = (int) (
                $purchaseOrderItem->unit_of_measure_id ?? 0
            );

            if ($poUnitId <= 0) {
                throw new BusinessException(
                    'The purchase order item has no configured unit.'
                );
            }

            $poProductUnit =
                $this->productUnits->findByProductAndUnit(
                    $productId,
                    $poUnitId
                );

            if (! $poProductUnit) {
                throw new BusinessException(
                    'The purchase order unit is not configured for this product.'
                );
            }

            $this->productUnitService->ensurePurchaseUnit(
                $poProductUnit
            );

            $productUnit =
                $this->productUnits->findByProductAndUnit(
                    $productId,
                    $unitId
                );

            if (! $productUnit) {
                throw new BusinessException(
                    'The selected GRN unit is not configured for this product.'
                );
            }

            $this->productUnitService->ensurePurchaseUnit(
                $productUnit
            );

            $quantity = (float) ($data['quantity_received'] ?? 0);
            $unitCost = (float) ($data['unit_cost'] ?? -1);

            $this->rules->ensurePositiveQuantity($quantity);
            $this->rules->ensureNonNegativeAmount($unitCost);

            $baseQuantity = $this->productUnitService->toBaseQuantity(
                $productUnit,
                $quantity
            );

            $baseUnitCost = $this->productUnitService->toBaseUnitCost(
                $productUnit,
                $unitCost
            );

            $lineTotal = round($quantity * $unitCost, 4);

            $item = $this->items->create([
                'goods_receipt_note_id' => $grn->id,

                'purchase_order_item_id' => $purchaseOrderItemId,
                'product_id' => $productId,
                'product_variant_id' => $variantId,

                'warehouse_id' => $grn->warehouse_id,
                'unit_id' => $unitId,

                'quantity_received' => $quantity,
                'base_quantity' => $baseQuantity,

                'unit_cost' => $unitCost,
                'base_unit_cost' => $baseUnitCost,
                'line_total' => $lineTotal,

                'tax_group_id' => $data['tax_group_id'] ?? null,
                'tax_rate' => $data['tax_rate'] ?? 0,
                'tax_amount' => $data['tax_amount'] ?? 0,
                'remarks' => $data['remarks'] ?? null,
            ]);

            $this->createTrackingRows($item, $product, $data);

            return $item->refresh();
        });
    }

    public function confirm(
        GoodsReceiptNote $grn
    ): GoodsReceiptNote {
        return $this->confirmationService->confirm($grn);
    }

    public function cancel(
        GoodsReceiptNote $grn
    ): GoodsReceiptNote {
        return $this->cancellationService->cancel($grn);
    }

    public function findByUuid(string $uuid): GoodsReceiptNote
    {
        $grn = $this->repository->findWithItemsByUuid($uuid);

        if (! $grn) {
            throw new BusinessException(
                'Goods receipt note not found.'
            );
        }

        return $grn;
    }

    protected function ensurePurchaseOrderReceivable(
        PurchaseOrder $purchaseOrder
    ): void {
        if (! in_array(
            $purchaseOrder->status,
            [
                PurchaseOrderStatusEnum::CONFIRMED->value,
                PurchaseOrderStatusEnum::PARTIALLY_RECEIVED->value,
            ],
            true
        )) {
            throw new BusinessException(
                'This purchase order cannot receive goods.'
            );
        }

        if (empty($purchaseOrder->supplier_id)) {
            throw new BusinessException(
                'The purchase order has no supplier.'
            );
        }
    }

    protected function createTrackingRows(
        GoodsReceiptNoteItem $item,
        object $product,
        array $data
    ): void {
        if (! $product->hasInventoryTracking()) {
            return;
        }

        if ($product->isBatchTracked()) {
            foreach ($data['batches'] ?? [] as $batchData) {
                $this->batchService->create($item, $batchData);
            }

            return;
        }

        if ($product->isSerialTracked()) {
            foreach ($data['serials'] ?? [] as $serialData) {
                $this->serialService->create($item, $serialData);
            }

            return;
        }

        throw new BusinessException(
            'Unsupported inventory tracking type.'
        );
    }

    protected function validateHeader(array $data): void
    {
        if (empty($data['purchase_order_id'])) {
            throw new BusinessException(
                'Purchase order is required.'
            );
        }

        if (empty($data['grn_date'])) {
            throw new BusinessException(
                'GRN date is required.'
            );
        }
    }

    protected function refreshTotalValuation(
        GoodsReceiptNote $grn
    ): void {
        $grn->load('items');

        $total = round(
            $grn->items->sum(
                fn (GoodsReceiptNoteItem $item) =>
                    (float) $item->line_total
            ),
            4
        );

        $this->repository->update($grn, [
            'total_received_valuation' => $total,
            'updated_by' => userId(),
        ]);
    }
}