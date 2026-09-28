<?php

namespace App\Modules\Receipt\Enums;

enum ReceiptStatusEnum: string
{
    case DRAFT = 'DRAFT';

    case CONFIRMED = 'CONFIRMED';

    case CANCELLED = 'CANCELLED';
}
