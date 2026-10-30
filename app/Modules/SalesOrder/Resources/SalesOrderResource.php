<?php

namespace App\Modules\SalesOrder\Resources;

use App\Modules\SalesOrder\Enums\SalesOrderStatusEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $statusValue = $this->status instanceof SalesOrderStatusEnum
            ? $this->status->value
            : (string) $this->status;

        $statusLabel = $this->status instanceof SalesOrderStatusEnum
            ? $this->status->label()
            : ucfirst((string) $this->status);

        return [
            'uuid' => $this->uuid,
            'order_number' => $this->order_number,
            'so_no' => $this->order_number,
            'order_date' => $this->order_date?->toDateString(),
            'expected_delivery_date' => $this->expected_delivery_date?->toDateString(),

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

            'payment_term' => $this->whenLoaded('paymentTerm', fn () => [
                'uuid' => $this->paymentTerm->uuid,
                'name' => $this->paymentTerm->name,
            ]),

            'sales_quotation' => $this->whenLoaded('salesQuotation', fn () => [
                'uuid' => $this->salesQuotation->uuid,
                'quotation_number' => $this->salesQuotation->quotation_number ?? $this->salesQuotation->quote_no ?? null,
            ]),

            'currency_code' => $this->currency_code,
            'exchange_rate' => (float) $this->exchange_rate,

            'subtotal_amount' => (float) $this->subtotal_amount,
            'subtotal' => (float) $this->subtotal_amount,
            'discount_amount' => (float) $this->discount_amount,
            'additional_charge_amount' => (float) $this->additional_charge_amount,
            'tax_total_amount' => (float) $this->tax_total_amount,
            'tax_amount' => (float) $this->tax_total_amount,
            'rounding_adjustment' => (float) $this->rounding_adjustment,
            'grand_total_amount' => (float) $this->grand_total_amount,
            'grand_total' => (float) $this->grand_total_amount,

            'notes' => $this->notes,
            'terms_and_conditions' => $this->terms_and_conditions,

            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,

            'items' => SalesOrderItemResource::collection($this->whenLoaded('items')),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
