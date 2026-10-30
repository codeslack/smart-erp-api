<?php

namespace App\Modules\Sales\Resources;

use App\Modules\Sales\Enums\SaleStatusEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $statusValue = $this->status instanceof SaleStatusEnum
            ? $this->status->value
            : (string) $this->status;

        $statusLabel = $this->status instanceof SaleStatusEnum
            ? $this->status->label()
            : ucfirst((string) $this->status);

        return [
            'uuid' => $this->uuid,
            'sale_no' => $this->sale_no,
            'sale_date' => $this->sale_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'customer_reference_number' => $this->customer_reference_number,

            'status' => $statusValue,
            'status_label' => $statusLabel,

            'customer' => $this->whenLoaded('customer', fn () => [
                'uuid' => $this->customer->uuid,
                'name' => $this->customer->name,
                'code' => $this->customer->code,
            ]),

            'warehouse' => $this->whenLoaded('warehouse', fn () => [
                'uuid' => $this->warehouse->uuid,
                'name' => $this->warehouse->name,
            ]),

            'sales_order' => $this->whenLoaded('salesOrder', fn () => [
                'uuid' => $this->salesOrder->uuid,
                'order_number' => $this->salesOrder->order_number,
            ]),

            'payment_term' => $this->whenLoaded('paymentTerm', fn () => [
                'uuid' => $this->paymentTerm->uuid,
                'name' => $this->paymentTerm->name,
            ]),

            'currency_code' => $this->currency_code,
            'exchange_rate' => (float) $this->exchange_rate,

            'subtotal' => (float) $this->subtotal,
            'discount_type' => $this->discount_type,
            'discount_rate' => (float) $this->discount_rate,
            'discount_amount' => (float) $this->discount_amount,
            'discount' => (float) $this->discount,

            'charges_subtotal' => (float) $this->charges_subtotal,
            'charges_tax_amount' => (float) $this->charges_tax_amount,
            'tax_amount' => (float) $this->tax_amount,
            'tax' => (float) $this->tax,
            'rounding_amount' => (float) $this->rounding_amount,
            'grand_total' => (float) $this->grand_total,

            'paid_amount' => (float) $this->paid_amount,
            'due_amount' => (float) $this->due_amount,

            'notes' => $this->notes,
            'terms_and_conditions' => $this->terms_and_conditions,

            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,

            'advance_allocations' => $this->whenLoaded('advanceAllocations', fn () => $this->advanceAllocations->map(fn ($allocation) => [
                'uuid' => $allocation->uuid,
                'allocated_amount' => (float) $allocation->allocated_amount,
                'receipt_no' => $allocation->source?->receipt_no,
            ])),

            'items' => SaleItemResource::collection($this->whenLoaded('items')),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
