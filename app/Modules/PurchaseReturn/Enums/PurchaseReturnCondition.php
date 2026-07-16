<?php

namespace App\Modules\PurchaseReturn\Enums;

enum PurchaseReturnCondition: string
{
    case GOOD = 'good';

    case UNUSED = 'unused';

    case DAMAGED = 'damaged';

    case EXPIRED = 'expired';

    case DEFECTIVE = 'defective';
}
