<?php

namespace App\Modules\Inventory\Enums;

enum InventoryCostLayerStatusEnum: string
{
    case OPEN = 'OPEN';

    case EXHAUSTED = 'EXHAUSTED';

    case REVERSED = 'REVERSED';
}