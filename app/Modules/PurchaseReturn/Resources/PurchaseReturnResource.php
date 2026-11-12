<?php

namespace App\Modules\PurchaseReturn\Resources;

use App\Modules\Purchase\Resources\PurchaseResource;
use App\Modules\Supplier\Resources\SupplierResource;
use App\Modules\User\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseReturnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'return_no' => $this->return_no,
            'purchase_uuid' => $this->purchase?->uuid,
            'supplier_uuid' => $this->supplier?->uuid,
            'return_date' => $this->return_date?->format('Y-m-d'),
            'currency_code' => $this->currency_code,
            'exchange_rate' => (float) $this->exchange_rate,
            'subtotal' => (float) $this->subtotal,
            'discount_amount' => (float) $this->discount_amount,
            'discount' => (float) $this->discount_amount,
            'tax_amount' => (float) $this->tax_amount,
            'tax' => (float) $this->tax_amount,
            'rounding_amount' => (float) $this->rounding_amount,
            'grand_total' => (float) $this->grand_total,
            'refund_amount' => (float) $this->refund_amount,
            'credited_amount' => (float) $this->credited_amount,
            'refund_type' => $this->refund_type instanceof \BackedEnum ? $this->refund_type->value : (string) $this->refund_type,
            'refund_account_uuid' => $this->refundAccount?->uuid,
            'return_reason' => $this->return_reason,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : (string) $this->status,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'notes' => $this->notes,
            'supplier' => SupplierResource::make($this->whenLoaded('supplier')),
            'purchase' => PurchaseResource::make($this->whenLoaded('purchase')),
            'approved_by_user' => UserResource::make($this->whenLoaded('approvedBy')),
            'items' => PurchaseReturnItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
