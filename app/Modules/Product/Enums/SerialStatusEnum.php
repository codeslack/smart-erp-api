<?php

namespace App\Modules\Product\Enums;

enum SerialStatusEnum: string
{
    case AVAILABLE = 'AVAILABLE';

    case RETURNED = 'RETURNED';

    case SOLD = 'SOLD';

    case DAMAGED = 'DAMAGED';

    case SCRAPPED = 'SCRAPPED';
}