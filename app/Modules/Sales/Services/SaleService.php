<?php

namespace App\Modules\Sales\Services;

use Illuminate\Support\Facades\DB;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use App\Modules\Sales\Enums\SaleStatus;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Inventory\Enums\StockTransactionType;
use App\Modules\Sales\Repositories\Contracts\SaleRepositoryInterface;

class SaleService
{
    public function __construct(
        protected SaleRepositoryInterface $repository,
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
                Sale::max('id') ?? 0
            ) + 1;

            $data['sale_no'] = sprintf(
                'SAL-%06d',
                $nextId
            );

            $data['status'] = SaleStatus::DRAFT;

            $sale = $this->repository->create(
                $data
            );

            $subtotal = 0;

            foreach ($items as $item) {

                $lineTotal =
                    $item['quantity']
                    *
                    $item['unit_price'];

                SaleItem::create([
                    'sale_id' => $sale->id,

                    'product_id' => $item['product_id'],

                    'warehouse_id' => $item['warehouse_id'],

                    'quantity' => $item['quantity'],

                    'unit_price' => $item['unit_price'],

                    'line_total' => $lineTotal,
                ]);

                $subtotal += $lineTotal;
            }

            $sale->update([
                'subtotal' => $subtotal,
                'grand_total' => $subtotal,
                'paid_amount' => 0,
                'due_amount'  => $subtotal,
            ]);

            return $sale
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
        Sale $sale
    ) {
        if (
            $sale->status !== SaleStatus::DRAFT
        ) {
            abort(
                422,
                'Sale already approved.'
            );
        }

        return DB::transaction(function () use ($sale) {

            foreach ($sale->items as $item) {

                $this->inventoryService->stockOut(
                    productId: $item->product_id,

                    warehouseId: $item->warehouse_id,

                    quantity: $item->quantity,

                    transactionType: StockTransactionType::SALE,

                    referenceType: Sale::class,

                    referenceId: $sale->id,

                    remarks: sprintf(
                        'Sale %s',
                        $sale->sale_no
                    ),
                );
            }

            $sale->update([
                'status' => SaleStatus::CONFIRMED,
            ]);

            return $sale
                ->fresh()
                ->load([
                    'customer',
                    'items',
                ]);
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
