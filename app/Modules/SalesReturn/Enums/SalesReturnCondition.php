<?php

namespace App\Modules\SalesReturn\Enums;

enum SalesReturnCondition: string
{
    case GOOD = 'good';

    case DAMAGED = 'damaged';

    case EXPIRED = 'expired';

    case DEFECTIVE = 'defective';
}
