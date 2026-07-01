<?php

namespace App\Modules\PurchaseReturn\Services;

use Illuminate\Support\Facades\DB;
use App\Modules\Product\Models\Product;
use App\Modules\Purchase\Models\Purchase;
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

    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {

            $items = $data['items'];

            unset($data['items']);

            $nextId = (
                PurchaseReturn::max('id') ?? 0
            ) + 1;

            $data['return_no'] = sprintf(
                'PRN-%06d',
                $nextId
            );

            $data['status'] =
                PurchaseReturnStatus::DRAFT;

            $purchaseReturn =
                $this->repository->create(
                    $data
                );

            $grandTotal = 0;

            foreach ($items as $item) {

                $lineTotal =
                    $item['quantity']
                    *
                    $item['unit_cost'];

                $product = Product::find(
                    $item['product_id']
                );

                abort_if(
                    !$product,
                    422,
                    'Invalid product.'
                );

                PurchaseReturnItem::create([
                    'purchase_return_id' => $purchaseReturn->id,

                    'product_id' => $item['product_id'],

                    'warehouse_id' => $item['warehouse_id'],

                    'quantity' => $item['quantity'],

                    'unit_cost' => $item['unit_cost'],

                    'line_total' => $lineTotal,
                ]);

                $grandTotal += $lineTotal;
            }

            $purchaseReturn->update([
                'grand_total' => $grandTotal,
            ]);

            return $purchaseReturn->load(
                'items'
            );
        });
    }

    public function update(
        int $id,
        array $data
    ) {
        return $this->repository->update(
            $id,
            $data
        );
    }

    public function approve(
        PurchaseReturn $purchaseReturn
    ) {
        if (
            $purchaseReturn->status !==
            PurchaseReturnStatus::DRAFT
        ) {
            abort(
                422,
                'Purchase Return already approved.'
            );
        }

        DB::transaction(function () use (
            $purchaseReturn
        ) {

            foreach (
                $purchaseReturn->items
                as $item
            ) {

                $this->inventoryService->stockOut(
                    productId: $item->product_id,

                    warehouseId: $item->warehouse_id,

                    quantity: $item->quantity,

                    transactionType:
                        StockTransactionType::PURCHASE_RETURN,

                    referenceType:
                        PurchaseReturn::class,

                    referenceId:
                        $purchaseReturn->id,

                    remarks: sprintf(
                        'Purchase Return %s',
                        $purchaseReturn->return_no
                    ),
                );
            }

            $purchaseReturn->update([
                'status' =>
                    PurchaseReturnStatus::CONFIRMED,
            ]);

            $purchaseReturn = $purchaseReturn
                ->fresh()
                ->load([
                    'supplier',
                    'purchase',
                    'items',
                ]);

            $this->accountingPostingService
                ->postPurchaseReturn(
                    $purchaseReturn
                );

        });

        return $purchaseReturn->fresh(
            ['items']
        );
    }

    public function delete(int $id)
    {
        return $this->repository->delete(
            $id
        );
    }
}
