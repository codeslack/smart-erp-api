<?php

namespace App\Modules\StockAdjustment\Services;

use Illuminate\Support\Facades\DB;
use App\Modules\Product\Models\Product;
use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Inventory\Enums\StockTransactionType;
use App\Modules\StockAdjustment\Models\StockAdjustment;
use App\Modules\StockAdjustment\Models\StockAdjustmentItem;
use App\Modules\StockAdjustment\Enums\StockAdjustmentStatus;
use App\Modules\StockAdjustment\Repositories\Contracts\StockAdjustmentRepositoryInterface;

class StockAdjustmentService
{
    public function __construct(
        protected StockAdjustmentRepositoryInterface $repository,
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
                StockAdjustment::max('id') ?? 0
            ) + 1;

            $data['adjustment_no'] = sprintf(
                'ADJ-%06d',
                $nextId
            );

            $data['status'] = StockAdjustmentStatus::DRAFT;

            $adjustment = $this->repository->create(
                $data
            );

            foreach ($items as $item) {

                $product = Product::find(
                    $item['product_id']
                );

                abort_if(
                    !$product,
                    422,
                    'Invalid product.'
                );

                $stock = ProductStock::query()
                    ->where(
                        'product_id',
                        $item['product_id']
                    )
                    ->where(
                        'warehouse_id',
                        $item['warehouse_id']
                    )
                    ->first();

                $systemQuantity =
                    $stock?->quantity ?? 0;

                $physicalQuantity =
                    $item['physical_quantity'];

                $adjustmentQuantity =
                    $physicalQuantity -
                    $systemQuantity;

                StockAdjustmentItem::create([
                    'stock_adjustment_id' => $adjustment->id,

                    'product_id' => $item['product_id'],

                    'warehouse_id' => $item['warehouse_id'],

                    'system_quantity' => $systemQuantity,

                    'physical_quantity' => $physicalQuantity,

                    'adjustment_quantity' => $adjustmentQuantity,

                    'remarks' => $item['remarks'] ?? null,
                ]);
            }

            return $adjustment->load(
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
        StockAdjustment $adjustment
    ) {
        abort_if(
            $adjustment->status !== StockAdjustmentStatus::DRAFT,
            422,
            'Adjustment already approved.'
        );

        DB::transaction(function () use ($adjustment) {

            foreach (
                $adjustment->items
                as $item
            ) {

                if (
                    $item->adjustment_quantity > 0
                ) {

                    $this->inventoryService->stockIn(
                        productId: $item->product_id,

                        warehouseId: $item->warehouse_id,

                        quantity: $item->adjustment_quantity,

                        transactionType: StockTransactionType::STOCK_ADJUSTMENT,

                        referenceType: StockAdjustment::class,

                        referenceId: $adjustment->id,

                        remarks: sprintf(
                            'Adjustment %s',
                            $adjustment->adjustment_no
                        ),
                    );
                }

                if (
                    $item->adjustment_quantity < 0
                ) {

                    $this->inventoryService->stockOut(
                        productId: $item->product_id,

                        warehouseId: $item->warehouse_id,

                        quantity: abs(
                            $item->adjustment_quantity
                        ),

                        transactionType: StockTransactionType::STOCK_ADJUSTMENT,

                        referenceType: StockAdjustment::class,

                        referenceId: $adjustment->id,

                        remarks: sprintf(
                            'Adjustment %s',
                            $adjustment->adjustment_no
                        ),
                    );
                }
            }

            $adjustment->update([
                'status' => StockAdjustmentStatus::CONFIRMED,
            ]);
        });

        return $adjustment
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
