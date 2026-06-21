<?php

namespace App\Modules\DeliveryNote\Services;

use Illuminate\Support\Facades\DB;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use App\Modules\Sales\Enums\SaleStatus;
use App\Modules\SalesOrder\Models\SalesOrder;
use App\Modules\SalesOrder\Models\SalesOrderItem;
use App\Modules\SalesOrder\Enums\SalesOrderStatus;
use App\Modules\DeliveryNote\Models\DeliveryNote;
use App\Modules\DeliveryNote\Models\DeliveryNoteItem;
use App\Modules\DeliveryNote\Enums\DeliveryNoteStatus;
use App\Modules\DeliveryNote\Repositories\Contracts\DeliveryNoteRepositoryInterface;

class DeliveryNoteService
{
    public function __construct(
        protected DeliveryNoteRepositoryInterface $repository
    ) {}

    public function getAll()
    {
        return $this->repository->paginate();
    }

    public function find(int $id)
    {
        return $this->repository->find(
            $id
        );
    }

    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {

            $salesOrder = SalesOrder::with(
                'items'
            )->findOrFail(
                $data['sales_order_id']
            );

            abort_if(
                $salesOrder->status !== SalesOrderStatus::APPROVED,
                422,
                'Sales Order must be approved.'
            );

            $items = $data['items'];

            unset(
                $data['items']
            );

            $nextId = (
                DeliveryNote::max('id') ?? 0
            ) + 1;

            $data['dn_no'] = sprintf(
                'DN-%06d',
                $nextId
            );

            $data['customer_id'] = $salesOrder->customer_id;

            $data['status'] = DeliveryNoteStatus::DRAFT;

            $deliveryNote = $this->repository->create(
                $data
            );

            $grandTotal = 0;

            foreach ($items as $item) {

                $soItem = SalesOrderItem::query()
                    ->where(
                        'sales_order_id',
                        $salesOrder->id
                    )
                    ->where(
                        'product_id',
                        $item['product_id']
                    )
                    ->where(
                        'warehouse_id',
                        $item['warehouse_id']
                    )
                    ->first();

                abort_if(
                    !$soItem,
                    422,
                    'Invalid sales order item.'
                );

                abort_if(
                    $soItem->pending_quantity <= 0,
                    422,
                    'This Sales Order item is already fully delivered.'
                );

                abort_if(
                    $item['delivered_quantity']
                        >
                        $soItem->pending_quantity,
                    422,
                    'Delivered quantity exceeds pending quantity.'
                );

                $lineTotal =
                    $item['delivered_quantity']
                    *
                    $soItem->unit_price;

                DeliveryNoteItem::create([
                    'delivery_note_id'   => $deliveryNote->id,
                    'product_id'         => $soItem->product_id,
                    'warehouse_id'       => $soItem->warehouse_id,
                    'ordered_quantity'   => $soItem->quantity,
                    'delivered_quantity' => $item['delivered_quantity'],
                    'pending_quantity'   => (
                        $soItem->pending_quantity
                        -
                        $item['delivered_quantity']
                    ),
                    'unit_price'         => $soItem->unit_price,
                    'line_total'         => $lineTotal,
                ]);

                $grandTotal += $lineTotal;
            }

            $deliveryNote->update([
                'grand_total' => $grandTotal,
            ]);

            return $deliveryNote->load(
                'items'
            );
        });
    }

    public function update(
        int $id,
        array $data
    ) {
        $deliveryNote = $this->find(
            $id
        );

        abort_if(
            $deliveryNote->status !== DeliveryNoteStatus::DRAFT,
            422,
            'Only draft Delivery Notes can be updated.'
        );

        return $this->repository->update(
            $id,
            $data
        );
    }

    public function deliver(
        DeliveryNote $deliveryNote
    ) {
        return DB::transaction(function () use ($deliveryNote) {

            abort_if(
                $deliveryNote->status !== DeliveryNoteStatus::DRAFT,
                422,
                'Only draft Delivery Notes can be delivered.'
            );

            foreach ($deliveryNote->items as $item) {

                $soItem = SalesOrderItem::query()
                    ->where(
                        'sales_order_id',
                        $deliveryNote->sales_order_id
                    )
                    ->where(
                        'product_id',
                        $item->product_id
                    )
                    ->where(
                        'warehouse_id',
                        $item->warehouse_id
                    )
                    ->first();

                $deliveredQty = $item->pending_quantity;

                $soItem->increment(
                    'delivered_quantity',
                    $deliveredQty
                );

                $soItem->decrement(
                    'pending_quantity',
                    $deliveredQty
                );

                $item->update([
                    'delivered_quantity' => $deliveredQty,
                    'pending_quantity'   => 0,
                ]);
            }

            $deliveryNote->update([
                'status' => DeliveryNoteStatus::DELIVERED,
            ]);

            return $deliveryNote->fresh(
                'items'
            );
        });
    }

    public function convertToSale(
        DeliveryNote $deliveryNote
    ) {
        return DB::transaction(function () use ($deliveryNote) {

            abort_if(
                $deliveryNote->status !== DeliveryNoteStatus::DELIVERED,
                422,
                'Delivery Note must be delivered first.'
            );

            $nextId = (
                Sale::max('id') ?? 0
            ) + 1;

            $sale = Sale::create([
                'tenant_id'   => tenant()->id,
                'customer_id' => $deliveryNote->customer_id,
                'sale_no'     => sprintf(
                    'SAL-%06d',
                    $nextId
                ),
                'sale_date'   => now(),
                'subtotal'    => $deliveryNote->grand_total,
                'grand_total' => $deliveryNote->grand_total,
                'status'      => SaleStatus::DRAFT,
                'notes'       => sprintf(
                    'Generated from %s',
                    $deliveryNote->dn_no
                ),
            ]);

            foreach ($deliveryNote->items as $item) {

                SaleItem::create([
                    'sale_id'      => $sale->id,
                    'product_id'   => $item->product_id,
                    'warehouse_id' => $item->warehouse_id,
                    'quantity'     => $item->delivered_quantity,
                    'unit_price'   => $item->unit_price,
                    'line_total'   => $item->line_total,
                ]);
            }

            $deliveryNote->update([
                'status' => DeliveryNoteStatus::CONVERTED,
            ]);

            return $sale->load(
                'items'
            );
        });
    }

    public function cancel(
        DeliveryNote $deliveryNote
    ) {
        abort_if(
            $deliveryNote->status !== DeliveryNoteStatus::DRAFT,
            422,
            'Only draft Delivery Notes can be cancelled.'
        );

        $deliveryNote->update([
            'status' => DeliveryNoteStatus::CANCELLED,
        ]);

        return $deliveryNote->fresh();
    }

    public function delete(
        int $id
    ) {
        $deliveryNote = $this->find(
            $id
        );

        abort_if(
            $deliveryNote->status !== DeliveryNoteStatus::DRAFT,
            422,
            'Only draft Delivery Notes can be deleted.'
        );

        return $this->repository->delete(
            $id
        );
    }
}
