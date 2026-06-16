<?php

namespace App\Modules\Purchase\Services;

use Illuminate\Support\Facades\DB;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Models\PurchaseItem;
use App\Modules\Purchase\Enums\PurchaseStatus;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Inventory\Enums\StockTransactionType;
use App\Modules\Purchase\Repositories\Contracts\PurchaseRepositoryInterface;

class PurchaseService
{
    public function __construct(
        protected PurchaseRepositoryInterface $repository,
        protected InventoryService $inventoryService,
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
                Purchase::max('id') ?? 0
            ) + 1;

            $data['purchase_no'] = sprintf(
                'PUR-%06d',
                $nextId
            );

            $data['status'] = PurchaseStatus::DRAFT;

            $purchase = $this->repository->create(
                $data
            );

            $subtotal = 0;

            foreach ($items as $item) {

                $lineTotal =
                    $item['quantity']
                    *
                    $item['unit_cost'];

                PurchaseItem::create([
                    'purchase_id' => $purchase->id,

                    'product_id' => $item['product_id'],

                    'warehouse_id' => $item['warehouse_id'],

                    'quantity' => $item['quantity'],

                    'unit_cost' => $item['unit_cost'],

                    'line_total' => $lineTotal,
                ]);

                $subtotal += $lineTotal;
            }

            $purchase->update([
                'subtotal' => $subtotal,
                'grand_total' => $subtotal,
            ]);

            return $purchase
                ->fresh()
                ->load('items');
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
        Purchase $purchase
    ) {
        if (
            $purchase->status !== PurchaseStatus::DRAFT
        ) {
            abort(
                422,
                'Purchase already approved.'
            );
        }

        return DB::transaction(function () use ($purchase) {

            foreach ($purchase->items as $item) {

                $this->inventoryService->stockIn(
                    productId: $item->product_id,

                    warehouseId: $item->warehouse_id,

                    quantity: $item->quantity,

                    transactionType: StockTransactionType::PURCHASE,

                    referenceType: Purchase::class,

                    referenceId: $purchase->id,

                    remarks: sprintf(
                        'Purchase %s',
                        $purchase->purchase_no
                    ),
                );
            }

            $purchase->update([
                'status' => PurchaseStatus::CONFIRMED,
            ]);

            return $purchase
                ->fresh()
                ->load('items');
        });
    }

    public function delete(
        int $id
    ) {
        return $this->repository->delete(
            $id
        );
    }
}
