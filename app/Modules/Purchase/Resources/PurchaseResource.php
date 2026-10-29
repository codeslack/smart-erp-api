<?php

namespace App\Modules\Purchase\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'purchase_no' => $this->purchase_no,
            'supplier_invoice_number' => $this->supplier_invoice_number,
            'supplier_invoice_date' => $this->supplier_invoice_date?->format('Y-m-d'),
            'purchase_date' => $this->purchase_date?->format('Y-m-d'),
            'due_date' => $this->due_date?->format('Y-m-d'),
            'currency_code' => $this->currency_code,
            'exchange_rate' => (float) $this->exchange_rate,
            'tax_calculation_mode' => $this->tax_calculation_mode ?? 'exclusive',
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : (string) $this->status,
            'status_label' => $this->status instanceof \App\Modules\Purchase\Enums\PurchaseStatusEnum
                ? $this->status->label()
                : ucfirst((string) $this->status),
            'supplier_id' => $this->supplier_id,
            'supplier_uuid' => $this->supplier?->uuid,
            'supplier' => $this->whenLoaded('supplier', fn () => [
                'uuid' => $this->supplier->uuid,
                'name' => $this->supplier->name,
                'code' => $this->supplier->code ?? null,
                'email' => $this->supplier->email ?? null,
                'phone' => $this->supplier->phone ?? null,
            ]),
            'warehouse_id' => $this->warehouse_id,
            'warehouse_uuid' => $this->warehouse?->uuid,
            'warehouse' => $this->whenLoaded('warehouse', fn () => [
                'uuid' => $this->warehouse->uuid,
                'name' => $this->warehouse->name,
                'code' => $this->warehouse->code ?? null,
            ]),
            'purchase_order_id' => $this->purchase_order_id,
            'purchase_order_uuid' => $this->purchaseOrder?->uuid,
            'purchase_order' => $this->whenLoaded('purchaseOrder', fn () => [
                'uuid' => $this->purchaseOrder->uuid,
                'order_number' => $this->purchaseOrder->order_number,
            ]),
            'goods_receipt_note_id' => $this->goods_receipt_note_id,
            'goods_receipt_note_uuid' => $this->goodsReceiptNote?->uuid,
            'goods_receipt_note' => $this->whenLoaded('goodsReceiptNote', fn () => [
                'uuid' => $this->goodsReceiptNote->uuid,
                'grn_number' => $this->goodsReceiptNote->grn_number,
            ]),
            'payment_term_id' => $this->payment_term_id,
            'subtotal' => (float) $this->subtotal,
            'discount_type' => $this->discount_type ?? 'none',
            'discount_rate' => (float) $this->discount_rate,
            'discount_amount' => (float) $this->discount_amount,
            'charges_subtotal' => (float) $this->charges_subtotal,
            'charges_tax_amount' => (float) $this->charges_tax_amount,
            'tax_amount' => (float) $this->tax_amount,
            'rounding_amount' => (float) $this->rounding_amount,
            'grand_total' => (float) $this->grand_total,
            'paid_amount' => (float) $this->paid_amount,
            'due_amount' => (float) $this->due_amount,
            'returned_amount' => (float) $this->returned_amount,
            'net_purchase_amount' => (float) $this->net_purchase_amount,
            'journal_entry_id' => $this->journal_entry_id,
            'notes' => $this->notes,
            'confirmed_at' => $this->confirmed_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'cancellation_reason' => $this->cancellation_reason,
            'charges' => PurchaseChargeResource::collection($this->whenLoaded('charges')),
            'items' => PurchaseItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
