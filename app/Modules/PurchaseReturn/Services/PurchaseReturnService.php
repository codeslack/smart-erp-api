<?php

namespace App\Modules\PurchaseReturn\Services;

use Illuminate\Support\Facades\DB;
use App\Modules\Purchase\Models\PurchaseItem;
use Illuminate\Validation\ValidationException;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Inventory\Enums\StockTransactionType;
use App\Modules\PurchaseReturn\Models\PurchaseReturn;
use App\Modules\PurchaseReturn\Models\PurchaseReturnItem;
use App\Modules\PurchaseReturn\Enums\PurchaseReturnStatus;
use App\Modules\Accounting\Services\Contracts\AccountingPostingServiceInterface;
use App\Modules\PurchaseReturn\Repositories\Contracts\PurchaseReturnRepositoryInterface;

class PurchaseReturnService
{
    public function __construct(
        protected PurchaseReturnRepositoryInterface $repository,
        protected InventoryService $inventoryService,
        protected AccountingPostingServiceInterface $accountingPostingService,
    ) {}

    public function getAll()
    {
        return $this->repository->paginate();
    }

    public function find(int $id)
    {
        return $this->repository->find($id);
    }

    public function create(array $data): PurchaseReturn
    {
        return DB::transaction(function () use ($data) {

            $items = $data['items'];

            unset($data['items']);

            $purchaseReturn =
                $this->createPurchaseReturn($data);

            $totals =
                $this->syncItems(
                    purchaseReturn: $purchaseReturn,
                    items: $items,
                    isUpdate: false
                );

            $purchaseReturn->update($totals);

            return $this->refreshRelations(
                $purchaseReturn
            );
        });
    }

    public function update(
        int $id,
        array $data
    ): PurchaseReturn {

        return DB::transaction(function () use ($id, $data) {

            $purchaseReturn =
                $this->repository->find($id);

            $this->ensureDraft($purchaseReturn);

            $items =
                $data['items'] ?? null;

            unset($data['items']);

            $purchaseReturn->update($data);

            if ($items !== null) {

                $purchaseReturn
                    ->items()
                    ->delete();

                $totals =
                    $this->syncItems(
                        purchaseReturn: $purchaseReturn,
                        items: $items,
                        isUpdate: true
                    );

                $purchaseReturn->update($totals);
            }

            return $this->refreshRelations(
                $purchaseReturn
            );
        });
    }

    public function approve(
        PurchaseReturn $purchaseReturn
    ): PurchaseReturn {

        return DB::transaction(function () use ($purchaseReturn) {

            $purchaseReturn =
                PurchaseReturn::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        $purchaseReturn->id
                    );

            $this->ensureDraft($purchaseReturn);

            foreach ($purchaseReturn->items as $item) {

                $this->inventoryService->validateStockOrFail(
                    productId: $item->product_id,
                    warehouseId: $item->warehouse_id,
                    requiredQuantity: $item->quantity,
                    productName: $item->product?->name
                );
            }

            $this->postInventory(
                $purchaseReturn
            );

            $purchaseReturn->update([

                'status' =>
                    PurchaseReturnStatus::CONFIRMED,

                'approved_by' =>
                    auth()->id(),

                'approved_at' =>
                    now(),
            ]);

            $purchaseReturn =
                $this->refreshRelations(
                    $purchaseReturn
                );

            $this->accountingPostingService
                ->postPurchaseReturn(
                    $purchaseReturn
                );

            return $purchaseReturn;
        });
    }

    public function delete(
        int $id
    ): bool {

        return DB::transaction(function () use ($id) {

            $purchaseReturn =
                $this->repository->find($id);

            $this->ensureDraft($purchaseReturn);

            return (bool)
                $this->repository->delete($id);
        });
    }

    private function createPurchaseReturn(
        array $data
    ): PurchaseReturn {

        $data['status'] =
            PurchaseReturnStatus::DRAFT;

        $data['return_no'] =
            'TEMP';

        $purchaseReturn =
            $this->repository->create($data);

        $purchaseReturn->update([

            'return_no' => sprintf(
                'PRN-%06d',
                $purchaseReturn->id
            ),
        ]);

        return $purchaseReturn->fresh();
    }

    private function syncItems(
        PurchaseReturn $purchaseReturn,
        array $items,
        bool $isUpdate = false
    ): array {

        $subtotal = 0;
        $discount = 0;
        $tax = 0;
        $grandTotal = 0;

        foreach ($items as $item) {

            $this->validatePurchaseItem(
                $item,
                $isUpdate
                    ? $purchaseReturn->id
                    : null
            );

            $totals =
                $this->calculateLineTotals(
                    $item
                );

            PurchaseReturnItem::create([

                'purchase_return_id' =>
                    $purchaseReturn->id,

                'purchase_item_id' =>
                    $item['purchase_item_id'],

                'product_id' =>
                    $item['product_id'],

                'warehouse_id' =>
                    $item['warehouse_id'],

                'quantity' =>
                    $item['quantity'],

                'unit_cost' =>
                    $item['unit_cost'],

                'discount' =>
                    $totals['discount'],

                'tax' =>
                    $totals['tax'],

                'line_total' =>
                    $totals['line_total'],

                'condition' =>
                    $item['condition'] ?? null,

                'reason' =>
                    $item['reason'] ?? null,
            ]);

            $subtotal += $totals['subtotal'];
            $discount += $totals['discount'];
            $tax += $totals['tax'];
            $grandTotal += $totals['line_total'];
        }

        return [

            'subtotal' =>
                $subtotal,

            'discount' =>
                $discount,

            'tax' =>
                $tax,

            'grand_total' =>
                $grandTotal,
        ];
    }

    private function calculateLineTotals(
        array $item
    ): array {

        $subtotal =
            $item['quantity']
            *
            $item['unit_cost'];

        $discount =
            $item['discount'] ?? 0;

        $tax =
            $item['tax'] ?? 0;

        return [

            'subtotal' =>
                $subtotal,

            'discount' =>
                $discount,

            'tax' =>
                $tax,

            'line_total' =>
                $subtotal - $discount + $tax,
        ];
    }

    private function validatePurchaseItem(
        array $item,
        ?int $excludePurchaseReturnId = null
    ): void {

        $purchaseItem = PurchaseItem::query()
            ->where('id', $item['purchase_item_id'])
            ->whereHas(
                'purchase',
                fn ($q) => $q->where(
                    'tenant_id',
                    tenant()->id
                )
            )
            ->firstOrFail();

        $returnedQty = PurchaseReturnItem::query()
            ->where(
                'purchase_item_id',
                $purchaseItem->id
            )
            ->when(
                $excludePurchaseReturnId,
                fn ($q) =>
                    $q->whereHas(
                        'purchaseReturn',
                        fn ($sub) =>
                            $sub->where(
                                'id',
                                '!=',
                                $excludePurchaseReturnId
                            )
                    )
            )
            ->whereHas(
                'purchaseReturn',
                fn ($q) =>
                    $q->whereNotIn(
                        'status',
                        [
                            PurchaseReturnStatus::DRAFT,
                            PurchaseReturnStatus::CONFIRMED,
                        ]
                    )
            )
            ->sum('quantity');

        $availableQty =
            $purchaseItem->quantity
            -
            $returnedQty;

        if ($item['quantity'] > $availableQty) {

            throw ValidationException::withMessages([
                'quantity' => [
                    sprintf(
                        'Return quantity exceeds available quantity. Available: %s, Requested: %s',
                        $availableQty,
                        $item['quantity']
                    )
                ]
            ]);
        }

        $currentStock =
            $this->inventoryService->availableStock(
                $item['product_id'],
                $item['warehouse_id']
            );

        if ($item['quantity'] > $currentStock) {

            throw ValidationException::withMessages([
                'quantity' => [
                    sprintf(
                        'Return quantity exceeds current stock. Current Stock: %s, Requested: %s',
                        $currentStock,
                        $item['quantity']
                    )
                ]
            ]);
        }
    }

    private function postInventory(
        PurchaseReturn $purchaseReturn
    ): void {

        foreach (
            $purchaseReturn->items
            as $item
        ) {

            $unitCost = (float) $item->purchaseItem->unit_cost;

            $this->inventoryService->stockOut(

                productId:
                    $item->product_id,

                warehouseId:
                    $item->warehouse_id,

                quantity:
                    $item->quantity,

                transactionType:
                    StockTransactionType::PURCHASE_RETURN,

                unitCost: $unitCost,

                referenceType:
                    PurchaseReturn::class,

                referenceId:
                    $purchaseReturn->id,

                remarks:
                    "Purchase Return {$purchaseReturn->return_no}",
            );
        }
    }

    private function ensureDraft(
        PurchaseReturn $purchaseReturn
    ): void {

        if (
            $purchaseReturn->status !==
            PurchaseReturnStatus::DRAFT
        ) {
            throw ValidationException::withMessages([
                'status' => [
                    'Only draft purchase returns can be modified.'
                ]
            ]);
        }
    }

    private function refreshRelations(
        PurchaseReturn $purchaseReturn
    ): PurchaseReturn {

        return $purchaseReturn
            ->fresh()
            ->load([

                'supplier',
                'purchase',
                'approvedBy',

                'items.product',
                'items.warehouse',
                'items.purchaseItem',
            ]);
    }
}