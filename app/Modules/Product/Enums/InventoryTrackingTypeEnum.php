<?php

namespace App\Modules\Product\Enums;

enum InventoryTrackingTypeEnum: string
{
    /*
    |--------------------------------------------------------------------------
    | No Tracking
    |--------------------------------------------------------------------------
    */

    case NONE = 'NONE';

    /*
    |--------------------------------------------------------------------------
    | Batch Tracking
    |--------------------------------------------------------------------------
    */

    case BATCH = 'BATCH';

    /*
    |--------------------------------------------------------------------------
    | Serial Tracking
    |--------------------------------------------------------------------------
    */

    case SERIAL = 'SERIAL';

    public static function values(): array
    {
        return array_column(
            self::cases(),
            'value'
        );
    }

    public function isNone(): bool
    {
        return $this === self::NONE;
    }

    public function isBatch(): bool
    {
        return $this === self::BATCH;
    }

    public function isSerial(): bool
    {
        return $this === self::SERIAL;
    }
}