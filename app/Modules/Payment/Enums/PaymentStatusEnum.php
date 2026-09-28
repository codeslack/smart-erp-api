<?php

namespace App\Modules\Payment\Enums;

enum PaymentStatusEnum: string
{
    case DRAFT = 'DRAFT';

    case CONFIRMED = 'CONFIRMED';

    case CANCELLED = 'CANCELLED';
}
