<?php

namespace App\Modules\Inventory\Enums;

enum ProductSerialStatusEnum: string
{
    case DRAFT = 'DRAFT';
    
    case AVAILABLE = 'AVAILABLE';

    case RETURNED = 'RETURNED';

    case SOLD = 'SOLD';

    case DAMAGED = 'DAMAGED';

    case SCRAPPED = 'SCRAPPED';
}