<?php

namespace App\Modules\Product\Enums;

enum BatchStatusEnum: string
{
    case ACTIVE = 'ACTIVE';

    case EXPIRED = 'EXPIRED';

    case DEPLETED = 'DEPLETED';

    case QUARANTINED = 'QUARANTINED';

    case INACTIVE = 'INACTIVE';
}
