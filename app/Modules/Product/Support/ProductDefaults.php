<?php

namespace App\Modules\Product\Support;

use App\Modules\Product\Enums\ProductTypeEnum;
use App\Modules\Product\Enums\ProductStatusEnum;
use App\Modules\Product\Enums\InventoryTrackingTypeEnum;

class ProductDefaults
{
    public static function for(
        string $productType
    ): array {

        return match ($productType) {

            ProductTypeEnum::MEDICINE->value => [

                'inventory_tracking_type'
                    => InventoryTrackingTypeEnum::BATCH->value,

                'track_inventory' => true,
                'track_batch' => true,
                'track_serial' => false,

                'has_expiry' => true,
                'has_warranty' => false,

                'requires_prescription' => false,

                'status'
                    => ProductStatusEnum::ACTIVE->value,
            ],

            ProductTypeEnum::MOBILE->value => [

                'inventory_tracking_type'
                    => InventoryTrackingTypeEnum::SERIAL->value,

                'track_inventory' => true,
                'track_batch' => false,
                'track_serial' => true,

                'has_expiry' => false,
                'has_warranty' => true,

                'status'
                    => ProductStatusEnum::ACTIVE->value,
            ],

            ProductTypeEnum::SERVICE->value => [

                'inventory_tracking_type'
                    => InventoryTrackingTypeEnum::NONE->value,

                'track_inventory' => false,
                'track_batch' => false,
                'track_serial' => false,

                'has_expiry' => false,
                'has_warranty' => false,

                'status'
                    => ProductStatusEnum::ACTIVE->value,
            ],

            default => [

                'inventory_tracking_type'
                    => InventoryTrackingTypeEnum::NORMAL->value,

                'track_inventory' => true,
                'track_batch' => false,
                'track_serial' => false,

                'has_expiry' => false,
                'has_warranty' => false,

                'status'
                    => ProductStatusEnum::ACTIVE->value,
            ],
        };
    }
}