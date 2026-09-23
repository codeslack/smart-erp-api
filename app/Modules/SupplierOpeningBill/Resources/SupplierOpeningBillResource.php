<?php

namespace App\Modules\SupplierOpeningBill\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierOpeningBillResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [

            'uuid' => $this->uuid,

            'bill_no' => $this->bill_no,
            'bill_date' => $this->bill_date,
            'due_date' => $this->due_date,

            'amount' => $this->amount,
            'balance_amount' => $this->balance_amount,
            'balance_type' => $this->balance_type,

            'paid_amount' => $this->paidAmount(),

            'is_debit' => $this->isDebit(),
            'is_credit' => $this->isCredit(),

            'notes' => $this->notes,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}