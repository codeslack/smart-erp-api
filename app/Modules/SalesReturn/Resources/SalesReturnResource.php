<?php

namespace App\Modules\SalesReturn\Resources;

use App\Modules\SalesReturn\Enums\SalesReturnStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesReturnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $statusValue = $this->status instanceof SalesReturnStatus
            ? $this->status->value
            : (string) $this->status;

        $statusLabel = $this->status instanceof SalesReturnStatus
            ? $this->status->label()
            : ucfirst((string) $this->status);

        return [
            'uuid' => $this->uuid,
            'return_no' => $this->return_no,
            'return_date' => $this->return_date?->toDateString(),

            'status' => $statusValue,
            'status_label' => $statusLabel,

            'customer' => $this->whenLoaded('customer', fn () => [
                'uuid' => $this->customer->uuid,
                'name' => $this->customer->name,
                'code' => $this->customer->code,
            ]),

            'sale' => $this->whenLoaded('sale', fn () => [
                'uuid' => $this->sale->uuid,
                'sale_no' => $this->sale->sale_no,
            ]),

            'warehouse' => $this->whenLoaded('warehouse', fn () => [
                'uuid' => $this->warehouse->uuid,
                'name' => $this->warehouse->name,
            ]),

            'refund_account' => $this->whenLoaded('refundAccount', fn () => [
                'uuid' => $this->refundAccount->uuid,
                'name' => $this->refundAccount->name,
                'code' => $this->refundAccount->code,
            ]),

            'currency_code' => $this->currency_code,
            'exchange_rate' => (float) $this->exchange_rate,

            'subtotal' => (float) $this->subtotal,
            'discount_amount' => (float) $this->discount_amount,
            'tax_amount' => (float) $this->tax_amount,
            'rounding_amount' => (float) $this->rounding_amount,
            'grand_total' => (float) $this->grand_total,

            'refund_amount' => (float) $this->refund_amount,
            'credited_amount' => (float) $this->credited_amount,
            'refund_type' => $this->refund_type?->value ?? (string) $this->refund_type,

            'return_reason' => $this->return_reason,
            'notes' => $this->notes,

            'approved_at' => $this->approved_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,

            'items' => SalesReturnItemResource::collection($this->whenLoaded('items')),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
