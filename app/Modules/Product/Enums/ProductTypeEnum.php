<?php

namespace App\Modules\Product\Enums;

enum ProductTypeEnum: string
{
    /*
    |--------------------------------------------------------------------------
    | Standard
    |--------------------------------------------------------------------------
    */

    case PRODUCT = 'PRODUCT';

    case SERVICE = 'SERVICE';

    /*
    |--------------------------------------------------------------------------
    | Manufacturing
    |--------------------------------------------------------------------------
    */

    case RAW_MATERIAL = 'RAW_MATERIAL';

    case FINISHED_GOOD = 'FINISHED_GOOD';

    /*
    |--------------------------------------------------------------------------
    | Inventory
    |--------------------------------------------------------------------------
    */

    case CONSUMABLE = 'CONSUMABLE';

    public static function values(): array
    {
        return array_column(
            self::cases(),
            'value'
        );
    }

    public function isInventoryItem(): bool
    {
        return $this !== self::SERVICE;
    }

    public function isService(): bool
    {
        return $this === self::SERVICE;
    }

    public function isManufacturingType(): bool
    {
        return in_array(
            $this,
            [
                self::RAW_MATERIAL,
                self::FINISHED_GOOD,
            ],
            true
        );
    }
}