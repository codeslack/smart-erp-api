<?php

namespace App\Modules\PurchaseOrder\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,

            'order_number' => $this->order_number,

            'order_date' =>
                $this->order_date?->toDateString(),

            'expected_delivery_date' =>
                $this->expected_delivery_date?->toDateString(),

            'supplier' => $this->whenLoaded(
                'supplier',
                function () {
                    return [
                        'uuid' => $this->supplier->uuid,
                        'name' => $this->supplier->name,
                        'code' => $this->supplier->code,
                    ];
                }
            ),

            'warehouse' => $this->whenLoaded(
                'warehouse',
                function () {
                    return [
                        'uuid' => $this->warehouse->uuid,
                        'name' => $this->warehouse->name,
                    ];
                }
            ),

            'payment_term' => $this->whenLoaded(
                'paymentTerm',
                function () {
                    return [
                        'uuid' => $this->paymentTerm->uuid,
                        'name' => $this->paymentTerm->name,
                    ];
                }
            ),

            'status' => $this->status,

            'currency_code' => $this->currency_code,

            'exchange_rate' => $this->exchange_rate,

            'amounts' => [
                'subtotal' =>
                    $this->subtotal_amount,

                'discount' =>
                    $this->discount_amount,

                'additional_charge' =>
                    $this->additional_charge_amount,

                'tax_total' =>
                    $this->tax_total_amount,

                'rounding_adjustment' =>
                    $this->rounding_adjustment,

                'grand_total' =>
                    $this->grand_total_amount,
            ],

            'items' => PurchaseOrderItemResource::collection(
                $this->whenLoaded('items')
            ),

            'notes' => $this->notes,

            'terms_and_conditions' =>
                $this->terms_and_conditions,

            'confirmed_at' =>
                $this->confirmed_at?->toISOString(),

            'cancelled_at' =>
                $this->cancelled_at?->toISOString(),

            'cancellation_reason' =>
                $this->cancellation_reason,

            'created_at' =>
                $this->created_at?->toISOString(),

            'updated_at' =>
                $this->updated_at?->toISOString(),
        ];
    }
}