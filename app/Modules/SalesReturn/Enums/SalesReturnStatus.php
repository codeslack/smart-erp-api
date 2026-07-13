<?php

namespace App\Modules\SalesReturn\Enums;

enum SalesReturnStatus: string
{
    case DRAFT = 'draft';

    case CONFIRMED = 'confirmed';

    case CANCELLED = 'cancelled';
}
