<?php

namespace App\Modules\Product\Enums;

enum SerialStatusEnum: string
{
    case AVAILABLE = 'AVAILABLE';

    case SOLD = 'SOLD';

    case RETURNED = 'RETURNED';

    case DAMAGED = 'DAMAGED';

    case SCRAPPED = 'SCRAPPED';
}