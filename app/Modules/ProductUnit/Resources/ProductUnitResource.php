<?php

namespace App\Modules\ProductUnit\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductUnitResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'uuid' => $this->uuid,

            'product_id' => $this->product_id,
            'unit_id' => $this->unit_id,

            'unit' => $this->whenLoaded(
                'unit',
                fn () => [
                    'uuid' => $this->unit->uuid,
                    'code' => $this->unit->code,
                    'name' => $this->unit->name,
                    'short_name' => $this->unit->short_name,
                ]
            ),

            'conversion_factor' => $this->conversion_factor,

            'is_purchase_unit' => $this->is_purchase_unit,
            'is_sales_unit' => $this->is_sales_unit,
            'is_active' => $this->is_active,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}