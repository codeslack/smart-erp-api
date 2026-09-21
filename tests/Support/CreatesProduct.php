<?php

namespace Tests\Support;

use App\Modules\Product\Models\Product;

use App\Modules\Product\Enums\ProductTypeEnum;
use App\Modules\Product\Enums\InventoryTrackingTypeEnum;

use App\Modules\SystemNumber\Enums\SystemNumberTypeEnum;

trait CreatesProduct
{
    protected function createProduct(
        array $attributes = []
    ): Product {

        return Product::create(
            array_merge(
                [
                    'tenant_id' => tenantId(),

                    'name' => 'Test Product',

                    'code' => nextSystemNumber(
                        SystemNumberTypeEnum::PRODUCT
                    ),

                    'sku' => 'SKU-' . uniqid(),

                    'slug' => 'product-' . uniqid(),

                    'product_type' =>
                        ProductTypeEnum::PRODUCT,

                    'inventory_tracking_type' =>
                        InventoryTrackingTypeEnum::NONE,

                    'purchase_price' => 10,

                    'selling_price' => 20,

                    'is_active' => true,
                ],
                $attributes
            )
        );
    }
}