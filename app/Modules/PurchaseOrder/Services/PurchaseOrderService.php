<?php

namespace App\Modules\PurchaseOrder\Services;

use Illuminate\Support\Facades\DB;
use App\Modules\Product\Models\Product;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Models\PurchaseItem;
use App\Modules\Purchase\Enums\PurchaseStatus;
use App\Modules\PurchaseOrder\Models\PurchaseOrder;
use App\Modules\PurchaseOrder\Models\PurchaseOrderItem;
use App\Modules\PurchaseOrder\Enums\PurchaseOrderStatus;
use App\Modules\PurchaseOrder\Repositories\Contracts\PurchaseOrderRepositoryInterface;

class PurchaseOrderService
{
    public function __construct(
        protected PurchaseOrderRepositoryInterface $repository
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
                PurchaseOrder::max('id') ?? 0
            ) + 1;

            $data['po_no'] = sprintf(
                'PO-%06d',
                $nextId
            );

            $data['status'] = PurchaseOrderStatus::DRAFT;

            $purchaseOrder = $this->repository->create(
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
                    $item['unit_cost'];

                PurchaseOrderItem::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'product_id'        => $item['product_id'],
                    'warehouse_id'      => $item['warehouse_id'],
                    'quantity'          => $item['quantity'],
                    'unit_cost'         => $item['unit_cost'],
                    'line_total'        => $lineTotal,
                ]);

                $subtotal += $lineTotal;
            }

            $purchaseOrder->update([
                'subtotal'    => $subtotal,
                'grand_total' => $subtotal,
            ]);

            return $purchaseOrder->load(
                'items'
            );
        });
    }

    public function update(
        int $id,
        array $data
    ) {
        $purchaseOrder = $this->find(
            $id
        );

        abort_if(
            $purchaseOrder->status !== PurchaseOrderStatus::DRAFT,
            422,
            'Only draft purchase orders can be updated.'
        );

        return $this->repository->update(
            $id,
            $data
        );
    }

    public function approve(
        PurchaseOrder $purchaseOrder
    ) {
        abort_if(
            $purchaseOrder->status !== PurchaseOrderStatus::DRAFT,
            422,
            'Purchase Order already approved.'
        );

        $purchaseOrder->update([
            'status' => PurchaseOrderStatus::APPROVED,
        ]);

        return $purchaseOrder->fresh();
    }

    public function convertToPurchase(
        PurchaseOrder $purchaseOrder
    ) {
        return DB::transaction(function () use ($purchaseOrder) {

            abort_if(
                $purchaseOrder->status !== PurchaseOrderStatus::APPROVED,
                422,
                'Purchase Order must be approved first.'
            );

            $purchaseOrder->loadMissing(
                'items'
            );

            $nextId = (
                Purchase::max('id') ?? 0
            ) + 1;

            $purchase = Purchase::create([
                'tenant_id'     => tenant()->id,
                'supplier_id'   => $purchaseOrder->supplier_id,
                'purchase_no'   => sprintf(
                    'PUR-%06d',
                    $nextId
                ),
                'purchase_date' => now(),
                'subtotal'      => $purchaseOrder->subtotal,
                'grand_total'   => $purchaseOrder->grand_total,
                'status'        => PurchaseStatus::DRAFT,
                'notes'         => sprintf(
                    'Generated from %s',
                    $purchaseOrder->po_no
                ),
            ]);

            foreach ($purchaseOrder->items as $item) {

                PurchaseItem::create([
                    'purchase_id'  => $purchase->id,
                    'product_id'   => $item->product_id,
                    'warehouse_id' => $item->warehouse_id,
                    'quantity'     => $item->quantity,
                    'unit_cost'    => $item->unit_cost,
                    'line_total'   => $item->line_total,
                ]);
            }

            $purchaseOrder->update([
                'status' => PurchaseOrderStatus::CONVERTED,
            ]);

            return $purchase->load(
                'items'
            );
        });
    }

    public function delete(
        int $id
    ) {
        $purchaseOrder = $this->find(
            $id
        );

        abort_if(
            $purchaseOrder->status !== PurchaseOrderStatus::DRAFT,
            422,
            'Only draft purchase orders can be deleted.'
        );

        return $this->repository->delete(
            $id
        );
    }
}
