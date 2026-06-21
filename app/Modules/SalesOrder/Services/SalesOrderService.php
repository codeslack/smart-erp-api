<?php

namespace App\Modules\SalesOrder\Services;

use Illuminate\Support\Facades\DB;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use App\Modules\Sales\Enums\SaleStatus;
use App\Modules\Product\Models\Product;
use App\Modules\SalesOrder\Models\SalesOrder;
use App\Modules\SalesOrder\Models\SalesOrderItem;
use App\Modules\DeliveryNote\Models\DeliveryNote;
use App\Modules\SalesOrder\Enums\SalesOrderStatus;
use App\Modules\DeliveryNote\Models\DeliveryNoteItem;
use App\Modules\DeliveryNote\Enums\DeliveryNoteStatus;
use App\Modules\SalesOrder\Repositories\Contracts\SalesOrderRepositoryInterface;


class SalesOrderService
{
    public function __construct(
        protected SalesOrderRepositoryInterface $repository
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
                SalesOrder::max('id') ?? 0
            ) + 1;

            $data['so_no'] = sprintf(
                'SO-%06d',
                $nextId
            );

            $data['status'] = SalesOrderStatus::DRAFT;

            $salesOrder = $this->repository->create(
                $data
            );

            $subtotal = 0;

            foreach ($items as $item) {

                $product = Product::find(
                    $item['product_id']
                );

                abort_if(
                    !$product,
                    422,
                    'Invalid product.'
                );

                $lineTotal =
                    $item['quantity']
                    *
                    $item['unit_price'];

                SalesOrderItem::create([
                    'sales_order_id'     => $salesOrder->id,
                    'product_id'         => $item['product_id'],
                    'warehouse_id'       => $item['warehouse_id'],
                    'quantity'           => $item['quantity'],
                    'delivered_quantity' => 0,
                    'pending_quantity'   => $item['quantity'],
                    'unit_price'         => $item['unit_price'],
                    'line_total'         => $lineTotal,
                ]);

                $subtotal += $lineTotal;
            }

            $salesOrder->update([
                'subtotal'    => $subtotal,
                'grand_total' => $subtotal,
            ]);

            return $salesOrder->load(
                'items'
            );
        });
    }

    public function update(
        int $id,
        array $data
    ) {
        $salesOrder = $this->find(
            $id
        );

        abort_if(
            $salesOrder->status !== SalesOrderStatus::DRAFT,
            422,
            'Only draft sales orders can be updated.'
        );

        return $this->repository->update(
            $id,
            $data
        );
    }

    public function approve(
        SalesOrder $salesOrder
    ) {
        abort_if(
            $salesOrder->status !== SalesOrderStatus::DRAFT,
            422,
            'Sales Order already approved.'
        );

        $salesOrder->update([
            'status' => SalesOrderStatus::APPROVED,
        ]);

        return $salesOrder->fresh();
    }

    public function convertToDeliveryNote(
        SalesOrder $salesOrder
    ) {
        return DB::transaction(function () use ($salesOrder) {

            abort_if(
                $salesOrder->status !== SalesOrderStatus::APPROVED,
                422,
                'Sales Order must be approved first.'
            );

            $salesOrder->loadMissing('items');

            $nextId = (
                DeliveryNote::max('id') ?? 0
            ) + 1;

            $grandTotal = 0;

            $deliveryNote = DeliveryNote::create([
                'tenant_id'      => tenant()->id,
                'sales_order_id' => $salesOrder->id,
                'customer_id'    => $salesOrder->customer_id,
                'dn_no'          => sprintf(
                    'DN-%06d',
                    $nextId
                ),
                'delivery_date'  => now(),
                'grand_total'    => 0,
                'status'         => DeliveryNoteStatus::DRAFT,
                'notes'          => sprintf(
                    'Generated from %s',
                    $salesOrder->so_no
                ),
            ]);

            foreach ($salesOrder->items as $item) {

                if ($item->pending_quantity <= 0) {
                    continue;
                }

                $lineTotal =
                    $item->pending_quantity
                    *
                    $item->unit_price;

                DeliveryNoteItem::create([
                    'delivery_note_id'   => $deliveryNote->id,
                    'product_id'         => $item->product_id,
                    'warehouse_id'       => $item->warehouse_id,
                    'ordered_quantity'   => $item->quantity,
                    'delivered_quantity' => 0,
                    'pending_quantity'   => $item->pending_quantity,
                    'unit_price'         => $item->unit_price,
                    'line_total'         => $lineTotal,
                ]);

                $grandTotal += $lineTotal;
            }

            $deliveryNote->update([
                'grand_total' => $grandTotal,
            ]);

            $salesOrder->update([
                'status' => SalesOrderStatus::CONVERTED_TO_DELIVERY,
            ]);

            return $deliveryNote->load('items');
        });
    }

    public function convertToSale(
        SalesOrder $salesOrder
    ) {
        return DB::transaction(function () use ($salesOrder) {

            abort_if(
                $salesOrder->status !== SalesOrderStatus::APPROVED,
                422,
                'Sales Order must be approved first.'
            );

            $salesOrder->loadMissing(
                'items'
            );

            $nextId = (
                Sale::max('id') ?? 0
            ) + 1;

            $sale = Sale::create([
                'tenant_id'   => tenant()->id,
                'customer_id' => $salesOrder->customer_id,
                'sale_no'     => sprintf(
                    'SAL-%06d',
                    $nextId
                ),
                'sale_date'   => now(),
                'subtotal'    => $salesOrder->subtotal,
                'grand_total' => $salesOrder->grand_total,
                'status'      => SaleStatus::DRAFT,
                'notes'       => sprintf(
                    'Generated from %s',
                    $salesOrder->so_no
                ),
            ]);

            foreach (
                $salesOrder->items as $item
            ) {

                SaleItem::create([
                    'sale_id'      => $sale->id,
                    'product_id'   => $item->product_id,
                    'warehouse_id' => $item->warehouse_id,
                    'quantity'     => $item->quantity,
                    'unit_price'   => $item->unit_price,
                    'line_total'   => $item->line_total,
                ]);
            }

            $salesOrder->update([
                'status' => SalesOrderStatus::CONVERTED_TO_SALE,
            ]);

            return $sale->load(
                'items'
            );
        });
    }

    public function delete(
        int $id
    ) {
        $salesOrder = $this->find(
            $id
        );

        abort_if(
            $salesOrder->status !== SalesOrderStatus::DRAFT,
            422,
            'Only draft sales orders can be deleted.'
        );

        return $this->repository->delete(
            $id
        );
    }
}
