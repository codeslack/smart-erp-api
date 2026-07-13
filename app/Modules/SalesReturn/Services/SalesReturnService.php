<?php

namespace App\Modules\SalesReturn\Services;

use Illuminate\Support\Facades\DB;
use App\Modules\Sales\Models\SaleItem;
use App\Modules\Inventory\Models\ProductStock;
use App\Modules\SalesReturn\Models\SalesReturn;
use App\Modules\SalesReturn\Models\SalesReturnItem;
use App\Modules\SalesReturn\Enums\SalesReturnStatus;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Inventory\Enums\StockTransactionType;
use App\Modules\Accounting\Services\Contracts\AccountingPostingServiceInterface;
use App\Modules\SalesReturn\Repositories\Contracts\SalesReturnRepositoryInterface;

class SalesReturnService
{
    public function __construct(
        protected SalesReturnRepositoryInterface $repository,
        protected InventoryService $inventoryService,
        protected AccountingPostingServiceInterface $accountingPostingService
    ) {}

    public function getAll()
    {
        return $this->repository->paginate();
    }

    public function find(int $id)
    {
        return $this->repository->find($id);
    }

    public function create(
        array $data
    )
    {
        return DB::transaction(function () use ($data) {

            $items = $data['items'];

            unset($data['items']);

            $data['status'] =
                SalesReturnStatus::DRAFT;

            $salesReturn =
                $this->repository->create(
                    $data
                );

            $salesReturn->update([
                'return_no' => sprintf(
                    'SRN-%06d',
                    $salesReturn->id
                ),
            ]);

            $this->syncItems(
                $salesReturn,
                $items
            );

            $this->recalculateTotals(
                $salesReturn
            );

            $this->calculateRefundAmounts(
                $salesReturn
            );

            return $salesReturn
                ->fresh()
                ->load([
                    'customer',
                    'sale',
                    'items.product',
                    'items.warehouse',
                    'items.saleItem',
                ]);
        });
    }

    public function update(
        int $id,
        array $data
    )
    {
        return DB::transaction(function () use (
            $id,
            $data
        ) {

            $salesReturn =
                $this->repository->find(
                    $id
                );

            abort_if(
                $salesReturn->status !==
                SalesReturnStatus::DRAFT,
                422,
                'Only draft returns can be updated.'
            );

            $items =
                $data['items'] ?? [];

            unset($data['items']);

            $salesReturn->update(
                $data
            );

            if (! empty($items)) {

                $salesReturn
                    ->items()
                    ->delete();

                $this->syncItems(
                    $salesReturn,
                    $items
                );
            }

            $this->recalculateTotals(
                $salesReturn
            );

            $this->calculateRefundAmounts(
                $salesReturn
            );

            return $salesReturn
                ->fresh()
                ->load([
                    'customer',
                    'sale',
                    'items.product',
                    'items.warehouse',
                    'items.saleItem',
                ]);
        });
    }


    public function approve(
        SalesReturn $salesReturn
    ) {

        abort_if(
            $salesReturn->status !== SalesReturnStatus::DRAFT,
            422,
            'Sales Return already approved.'
        );

        return DB::transaction(function () use (
            $salesReturn
        ) {

            $salesReturn->loadMissing('items');

            foreach (
                $salesReturn->items
                as $item
            ) {

                $productStock =
                    ProductStock::query()

                    ->where(
                        'product_id',
                        $item->product_id
                    )

                    ->where(
                        'warehouse_id',
                        $item->warehouse_id
                    )

                    ->first();

                abort_if(
                    ! $productStock,
                    422,
                    "Stock record not found for product {$item->product_id}"
                );

                $unitCost =
                    $productStock?->average_cost
                    ?? 0;

                $this->inventoryService->stockIn(

                    productId: $item->product_id,

                    warehouseId: $item->warehouse_id,

                    quantity: $item->quantity,

                    unitCost: $unitCost,

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
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            $salesReturn = $salesReturn
                ->fresh()
                ->load([
                    'customer',
                    'sale',
                    'items.product',
                    'items.warehouse',
                    'items.saleItem',
                ]);

            $this->accountingPostingService
                ->postSalesReturn(
                    $salesReturn
                );

            return $salesReturn;
        });
    }

    public function delete(
        int $id
    ) {

        return DB::transaction(function () use (
            $id
        ) {

            $salesReturn =
                $this->repository->find(
                    $id
                );

            abort_if(

                $salesReturn->status !==
                SalesReturnStatus::DRAFT,

                422,

                'Approved sales returns cannot be deleted.'
            );

            return $this->repository->delete(
                $id
            );
        });
    }

    protected function validateReturnQuantity(
        SalesReturn $salesReturn,
        SaleItem $saleItem,
        float $requestQty
    ): void {

        $alreadyReturned =
            SalesReturnItem::query()

                ->where(
                    'sale_item_id',
                    $saleItem->id
                )

                ->whereHas(
                    'salesReturn',
                    function ($query) use (
                        $salesReturn
                    ) {

                        $query
                            ->where(
                                'status',
                                SalesReturnStatus::CONFIRMED
                            )
                            ->where(
                                'id',
                                '!=',
                                $salesReturn->id
                            );
                    }
                )

                ->sum(
                    'quantity'
                );

        $availableToReturn =
            $saleItem->quantity
            -
            $alreadyReturned;

        abort_if(
            $requestQty > $availableToReturn,
            422,
            sprintf(
                'Only %s quantity available for return.',
                $availableToReturn
            )
        );
    }

    protected function syncItems(
        SalesReturn $salesReturn,
        array $items
    ): void {

        foreach ($items as $item) {

            $saleItem =
                SaleItem::query()
                    ->where(
                        'id',
                        $item['sale_item_id']
                    )
                    ->where(
                        'sale_id',
                        $salesReturn->sale_id
                    )
                    ->first();

            abort_if(
                ! $saleItem,
                422,
                'Invalid sale item.'
            );

            abort_if(
                $saleItem->product_id
                !=
                $item['product_id'],
                422,
                'Product mismatch.'
            );

            abort_if(
                $saleItem->warehouse_id
                !=
                $item['warehouse_id'],
                422,
                'Warehouse mismatch.'
            );

            $this->validateReturnQuantity(
                salesReturn: $salesReturn,
                saleItem: $saleItem,
                requestQty: $item['quantity']
            );

            $lineTotal =
                (
                    $item['quantity']
                    *
                    $item['unit_price']
                )
                -
                (
                    $item['discount']
                    ?? 0
                )
                +
                (
                    $item['tax']
                    ?? 0
                );

            SalesReturnItem::create([

                'sales_return_id'
                    => $salesReturn->id,

                'sale_item_id'
                    => $saleItem->id,

                'product_id'
                    => $item['product_id'],

                'warehouse_id'
                    => $item['warehouse_id'],

                'quantity'
                    => $item['quantity'],

                'unit_price'
                    => $item['unit_price'],

                'discount'
                    => $item['discount']
                    ?? 0,

                'tax'
                    => $item['tax']
                    ?? 0,

                'line_total'
                    => $lineTotal,

                'condition'
                    => $item['condition']
                    ?? null,

                'reason'
                    => $item['reason']
                    ?? null,
            ]);
        }
    }

    protected function recalculateTotals(
        SalesReturn $salesReturn
    ): void {

        $salesReturn->loadMissing(
            'items'
        );

        $subtotal =
            $salesReturn->items->sum(
                function ($item) {

                    return
                        $item->quantity
                        *
                        $item->unit_price;
                }
            );

        $discount =
            $salesReturn->items->sum(
                'discount'
            );

        $tax =
            $salesReturn->items->sum(
                'tax'
            );

        $grandTotal =
            $subtotal
            -
            $discount
            +
            $tax;

        $salesReturn->update([

            'subtotal'
                => $subtotal,

            'discount'
                => $discount,

            'tax'
                => $tax,

            'grand_total'
                => $grandTotal,
        ]);
    }

    protected function calculateRefundAmounts(
        SalesReturn $salesReturn
    ): void {

        $refundAmount = 0;

        $creditedAmount = 0;

        if (
            $salesReturn->refund_type
            ===
            \App\Modules\SalesReturn\Enums\SalesReturnRefundType::CREDIT_NOTE
        ) {

            $creditedAmount =
                $salesReturn->grand_total;
        }

        if (
            $salesReturn->refund_type
            ===
            \App\Modules\SalesReturn\Enums\SalesReturnRefundType::CASH_REFUND
        ) {

            $refundAmount =
                $salesReturn->grand_total;
        }

        if (
            $salesReturn->refund_type
            ===
            \App\Modules\SalesReturn\Enums\SalesReturnRefundType::BANK_REFUND
        ) {

            $refundAmount =
                $salesReturn->grand_total;
        }

        $salesReturn->update([

            'refund_amount' => $refundAmount,

            'credited_amount' => $creditedAmount,
        ]);
    }

}
