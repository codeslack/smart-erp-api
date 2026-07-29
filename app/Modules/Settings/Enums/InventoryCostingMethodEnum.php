<?php

namespace App\Modules\Settings\Enums;

enum InventoryCostingMethodEnum: string
{
    case WEIGHTED_AVERAGE = 'WEIGHTED_AVERAGE';

    case FIFO = 'FIFO';
}
