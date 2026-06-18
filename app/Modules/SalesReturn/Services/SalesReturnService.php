<?php

namespace App\Modules\SalesReturn\Services;

use Illuminate\Support\Facades\DB;
use App\Modules\Sales\Models\Sale;
use App\Modules\Product\Models\Product;
use App\Modules\SalesReturn\Models\SalesReturn;
use App\Modules\SalesReturn\Models\SalesReturnItem;
use App\Modules\SalesReturn\Enums\SalesReturnStatus;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Inventory\Enums\StockTransactionType;
use App\Modules\SalesReturn\Repositories\Contracts\SalesReturnRepositoryInterface;

class SalesReturnService
{
    public function __construct(
        protected SalesReturnRepositoryInterface $repository,
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
                SalesReturn::max('id') ?? 0
            ) + 1;

            $data['return_no'] = sprintf(
                'SRN-%06d',
                $nextId
            );

            $data['status'] = SalesReturnStatus::DRAFT;

            $salesReturn = $this->repository->create(
                $data
            );

            $grandTotal = 0;

            foreach ($items as $item) {

                $lineTotal =
                    $item['quantity']
                    *
                    $item['unit_price'];

                $product = Product::find(
                    $item['product_id']
                );

                abort_if(
                    !$product,
                    422,
                    'Invalid product.'
                );

                SalesReturnItem::create([
                    'sales_return_id' => $salesReturn->id,

                    'product_id' => $item['product_id'],

                    'warehouse_id' => $item['warehouse_id'],

                    'quantity' => $item['quantity'],

                    'unit_price' => $item['unit_price'],

                    'line_total' => $lineTotal,
                ]);

                $grandTotal += $lineTotal;
            }

            $salesReturn->update([
                'grand_total' => $grandTotal,
            ]);

            return $salesReturn->load(
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
        SalesReturn $salesReturn
    ) {
        abort_if(
            $salesReturn->status !== SalesReturnStatus::DRAFT,
            422,
            'Sales Return already approved.'
        );

        DB::transaction(function () use ($salesReturn) {

            foreach (
                $salesReturn->items
                as $item
            ) {

                $this->inventoryService->stockIn(
                    productId: $item->product_id,

                    warehouseId: $item->warehouse_id,

                    quantity: $item->quantity,

                    transactionType: StockTransactionType::SALES_RETURN,

                    referenceType: SalesReturn::class,

                    referenceId: $salesReturn->id,

                    remarks: sprintf(
                        'Sales Return %s',
                        $salesReturn->return_no
                    ),
                );
            }

            $salesReturn->update([
                'status' => SalesReturnStatus::CONFIRMED,
            ]);
        });

        return $salesReturn
            ->fresh()
            ->load('items');
    }

    public function delete(int $id)
    {
        return $this->repository->delete(
            $id
        );
    }
}
