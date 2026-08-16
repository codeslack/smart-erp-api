<?php

namespace App\Modules\Inventory\Enums;

enum ProductSerialStatusEnum: string
{
    case AVAILABLE = 'AVAILABLE';

    case RETURNED = 'RETURNED';

    case SOLD = 'SOLD';

    case DAMAGED = 'DAMAGED';

    case SCRAPPED = 'SCRAPPED';
}