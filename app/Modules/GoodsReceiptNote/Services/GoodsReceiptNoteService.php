<?php

namespace App\Modules\GoodsReceiptNote\Services;

use Illuminate\Support\Facades\DB;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Models\PurchaseItem;
use App\Modules\Purchase\Enums\PurchaseStatus;
use App\Modules\PurchaseOrder\Models\PurchaseOrder;
use App\Modules\PurchaseOrder\Models\PurchaseOrderItem;
use App\Modules\PurchaseOrder\Enums\PurchaseOrderStatus;
use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNote;
use App\Modules\GoodsReceiptNote\Models\GoodsReceiptNoteItem;
use App\Modules\GoodsReceiptNote\Enums\GoodsReceiptNoteStatus;
use App\Modules\GoodsReceiptNote\Repositories\Contracts\GoodsReceiptNoteRepositoryInterface;

class GoodsReceiptNoteService
{
    public function __construct(
        protected GoodsReceiptNoteRepositoryInterface $repository
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

            $purchaseOrder = PurchaseOrder::with(
                'items'
            )->findOrFail(
                $data['purchase_order_id']
            );

            abort_if(
                $purchaseOrder->status !== PurchaseOrderStatus::APPROVED,
                422,
                'Purchase Order must be approved.'
            );

            $items = $data['items'];

            abort_if(
                empty($items),
                422,
                'At least one item is required.'
            );

            unset(
                $data['items']
            );

            $nextId = (
                GoodsReceiptNote::max('id') ?? 0
            ) + 1;

            $data['grn_no'] = sprintf(
                'GRN-%06d',
                $nextId
            );

            $data['supplier_id'] = $purchaseOrder->supplier_id;

            $data['status'] = GoodsReceiptNoteStatus::DRAFT;

            $grn = $this->repository->create(
                $data
            );

            $grandTotal = 0;

            foreach ($items as $item) {

                $poItem = PurchaseOrderItem::query()
                    ->where(
                        'purchase_order_id',
                        $purchaseOrder->id
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
                    !$poItem,
                    422,
                    'Invalid purchase order item.'
                );

                abort_if(
                    $poItem->pending_quantity <= 0,
                    422,
                    'This Purchase Order item is already fully received.'
                );

                abort_if(
                    $item['received_quantity']
                        >
                        $poItem->pending_quantity,
                    422,
                    'Received quantity exceeds pending quantity.'
                );

                abort_if(
                    $item['received_quantity'] <= 0,
                    422,
                    'Received quantity must be greater than zero.'
                );

                $lineTotal =
                    $item['received_quantity']
                    *
                    $poItem->unit_cost;

                GoodsReceiptNoteItem::create([
                    'goods_receipt_note_id' => $grn->id,
                    'product_id'            => $poItem->product_id,
                    'warehouse_id'          => $poItem->warehouse_id,
                    'ordered_quantity'      => $poItem->quantity,
                    'received_quantity'     => $item['received_quantity'],
                    'pending_quantity'      => (
                        $poItem->pending_quantity
                        -
                        $item['received_quantity']
                    ),
                    'unit_cost'             => $poItem->unit_cost,
                    'line_total'            => $lineTotal,
                ]);

                $grandTotal += $lineTotal;
            }

            $grn->update([
                'grand_total' => $grandTotal,
            ]);

            return $grn->load(
                'items'
            );
        });
    }

    public function update(
        int $id,
        array $data
    ) {
        $grn = $this->find(
            $id
        );

        abort_if(
            $grn->status !== GoodsReceiptNoteStatus::DRAFT,
            422,
            'Only draft GRN can be updated.'
        );

        return $this->repository->update(
            $id,
            $data
        );
    }

    public function receive(
        GoodsReceiptNote $grn
    ) {
        return DB::transaction(function () use ($grn) {

            abort_if(
                $grn->status !== GoodsReceiptNoteStatus::DRAFT,
                422,
                'Only draft GRN can be received.'
            );

            $grn->load(
                'items'
            );

            foreach (
                $grn->items as $item
            ) {

                $poItem = PurchaseOrderItem::query()
                    ->where(
                        'purchase_order_id',
                        $grn->purchase_order_id
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

                $poItem->increment(
                    'received_quantity',
                    $item->received_quantity
                );

                $poItem->decrement(
                    'pending_quantity',
                    $item->received_quantity
                );
            }

            $grn->update([
                'status' => GoodsReceiptNoteStatus::RECEIVED,
            ]);

            return $grn->fresh(
                'items'
            );
        });
    }

    public function convertToPurchase(
        GoodsReceiptNote $grn
    ) {
        return DB::transaction(function () use ($grn) {

            abort_if(
                $grn->status !== GoodsReceiptNoteStatus::RECEIVED,
                422,
                'GRN must be received first.'
            );

            $nextId = (
                Purchase::max('id') ?? 0
            ) + 1;

            $purchase = Purchase::create([
                'tenant_id'     => tenant()->id,
                'supplier_id'   => $grn->supplier_id,
                'purchase_no'   => sprintf(
                    'PUR-%06d',
                    $nextId
                ),
                'purchase_date' => $grn->received_date,
                'subtotal'      => $grn->grand_total,
                'grand_total'   => $grn->grand_total,
                'status'        => PurchaseStatus::DRAFT,
                'notes'         => sprintf(
                    'Generated from %s',
                    $grn->grn_no
                ),
            ]);

            $grn->load(
                'items'
            );

            foreach (
                $grn->items as $item
            ) {

                PurchaseItem::create([
                    'purchase_id'  => $purchase->id,
                    'product_id'   => $item->product_id,
                    'warehouse_id' => $item->warehouse_id,
                    'quantity'     => $item->received_quantity,
                    'unit_cost'    => $item->unit_cost,
                    'line_total'   => $item->line_total,
                ]);
            }

            $grn->update([
                'status' => GoodsReceiptNoteStatus::CONVERTED,
            ]);

            return $purchase->load(
                'items'
            );
        });
    }

    public function cancel(
        GoodsReceiptNote $grn
    ) {
        abort_if(
            $grn->status !== GoodsReceiptNoteStatus::DRAFT,
            422,
            'Only draft GRN can be cancelled.'
        );

        $grn->update([
            'status' => GoodsReceiptNoteStatus::CANCELLED,
        ]);

        return $grn->fresh();
    }

    public function delete(
        int $id
    ) {
        $grn = $this->find(
            $id
        );

        abort_if(
            $grn->status !== GoodsReceiptNoteStatus::DRAFT,
            422,
            'Only draft GRN can be deleted.'
        );

        return $this->repository->delete(
            $id
        );
    }
}
