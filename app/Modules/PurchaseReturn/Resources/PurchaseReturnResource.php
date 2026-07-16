<?php

namespace App\Modules\PurchaseReturn\Resources;

use Illuminate\Http\Request;
use App\Modules\User\Resources\UserResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Modules\Purchase\Resources\PurchaseResource;
use App\Modules\Supplier\Resources\SupplierResource;

class PurchaseReturnResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(
        Request $request
    ): array {
        return [

            'id'
                => $this->id,

            'return_no'
                => $this->return_no,

            'purchase_id'
                => $this->purchase_id,

            'supplier_id'
                => $this->supplier_id,

            'return_date'
                => $this->return_date,

            'subtotal'
                => $this->subtotal,

            'discount'
                => $this->discount,

            'tax'
                => $this->tax,

            'grand_total'
                => $this->grand_total,

            'refund_amount'
                => $this->refund_amount,

            'credited_amount'
                => $this->credited_amount,

            'refund_type'
                => $this->refund_type?->value,

            'return_reason'
                => $this->return_reason,

            'status'
                => $this->status?->value,

            'approved_by'
                => $this->approved_by,

            'approved_at'
                => $this->approved_at,

            'notes'
                => $this->notes,

            'supplier'
                => SupplierResource::make(
                    $this->whenLoaded(
                        'supplier'
                    )
                ),

            'purchase'
                => PurchaseResource::make(
                    $this->whenLoaded(
                        'purchase'
                    )
                ),

            'approved_by_user'
                => UserResource::make(
                    $this->whenLoaded(
                        'approvedBy'
                    )
                ),

            'items'
                => PurchaseReturnItemResource::collection(
                    $this->whenLoaded(
                        'items'
                    )
                ),

            'created_at'
                => $this->created_at,

            'updated_at'
                => $this->updated_at,
        ];
    }
}