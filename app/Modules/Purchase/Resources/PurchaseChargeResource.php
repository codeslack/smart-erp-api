<?php

namespace App\Modules\Purchase\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseChargeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'charge_type' => $this->charge_type,
            'name' => $this->name,
            'calculation_method' => $this->calculation_method,
            'calculation_value' => (float) $this->calculation_value,
            'amount' => (float) $this->amount,
            'tax_id' => $this->tax_id,
            'tax_rate' => (float) $this->tax_rate,
            'tax_amount' => (float) $this->tax_amount,
            'treatment' => $this->treatment,
            'allocation_method' => $this->allocation_method,
            'affects_inventory_cost' => (bool) $this->affects_inventory_cost,
            'chart_of_account_id' => $this->chart_of_account_id,
            'chart_of_account_uuid' => $this->chartOfAccount?->uuid,
            'chart_of_account' => $this->whenLoaded('chartOfAccount', fn () => [
                'uuid' => $this->chartOfAccount->uuid,
                'name' => $this->chartOfAccount->name,
                'code' => $this->chartOfAccount->code ?? null,
            ]),
            'tax' => $this->whenLoaded('tax', fn () => [
                'uuid' => $this->tax->uuid,
                'name' => $this->tax->name,
            ]),
            'sort_order' => (int) $this->sort_order,
        ];
    }
}
