<?php

namespace App\Modules\Product\Enums;

enum InventoryTrackingTypeEnum: string
{
    case NORMAL = 'NORMAL';

    case BATCH = 'BATCH';

    case SERIAL = 'SERIAL';

    case NONE = 'NONE';
}