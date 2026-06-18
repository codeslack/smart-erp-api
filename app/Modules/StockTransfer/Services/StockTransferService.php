<?php

namespace App\Modules\StockTransfer\Services;

use Illuminate\Support\Facades\DB;
use App\Modules\Product\Models\Product;
use App\Modules\Inventory\Models\ProductStock;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Inventory\Enums\StockTransactionType;
use App\Modules\StockTransfer\Models\StockTransfer;
use App\Modules\StockTransfer\Models\StockTransferItem;
use App\Modules\StockTransfer\Enums\StockTransferStatus;
use App\Modules\StockTransfer\Repositories\Contracts\StockTransferRepositoryInterface;

class StockTransferService
{
    public function __construct(
        protected StockTransferRepositoryInterface $repository,
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

            abort_if(
                $data['from_warehouse_id']
                ===
                $data['to_warehouse_id'],
                422,
                'Source and destination warehouse cannot be the same.'
            );

            $nextId = (
                StockTransfer::max('id') ?? 0
            ) + 1;

            $data['transfer_no'] = sprintf(
                'TRF-%06d',
                $nextId
            );

            $data['status'] = StockTransferStatus::DRAFT;

            $transfer = $this->repository->create(
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

                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,

                    'product_id' => $item['product_id'],

                    'quantity' => $item['quantity'],
                ]);
            }

            return $transfer->load(
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
        StockTransfer $transfer
    ) {
        abort_if(
            $transfer->status !== StockTransferStatus::DRAFT,
            422,
            'Transfer already approved.'
        );

        DB::transaction(function () use ($transfer) {

            foreach ($transfer->items as $item) {

                $stock = ProductStock::query()
                    ->where(
                        'tenant_id',
                        tenant()->id
                    )
                    ->where(
                        'product_id',
                        $item->product_id
                    )
                    ->where(
                        'warehouse_id',
                        $transfer->from_warehouse_id
                    )
                    ->first();

                $availableStock =
                    $stock?->quantity ?? 0;

                abort_if(
                    $availableStock < $item->quantity,
                    422,
                    sprintf(
                        'Insufficient stock for product #%s.',
                        $item->product_id
                    )
                );

                $this->inventoryService->stockOut(
                    productId: $item->product_id,

                    warehouseId: $transfer->from_warehouse_id,

                    quantity: $item->quantity,

                    transactionType: StockTransactionType::TRANSFER_OUT,

                    referenceType: StockTransfer::class,

                    referenceId: $transfer->id,

                    remarks: sprintf(
                        'Transfer %s',
                        $transfer->transfer_no
                    ),
                );

                $this->inventoryService->stockIn(
                    productId: $item->product_id,

                    warehouseId: $transfer->to_warehouse_id,

                    quantity: $item->quantity,

                    transactionType: StockTransactionType::TRANSFER_IN,

                    referenceType: StockTransfer::class,

                    referenceId: $transfer->id,

                    remarks: sprintf(
                        'Transfer %s',
                        $transfer->transfer_no
                    ),
                );
            }

            $transfer->update([
                'status' => StockTransferStatus::CONFIRMED,
            ]);
        });

        return $transfer
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
