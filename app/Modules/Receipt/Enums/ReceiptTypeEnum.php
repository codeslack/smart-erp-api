<?php

namespace App\Modules\Receipt\Enums;

enum ReceiptTypeEnum: string
{
    case CUSTOMER_PAYMENT = 'CUSTOMER_PAYMENT';

    case CUSTOMER_ADVANCE = 'CUSTOMER_ADVANCE';

    case OPENING_BALANCE_ADJUSTMENT =
        'OPENING_BALANCE_ADJUSTMENT';

    case REFUND_RECEIVED = 'REFUND_RECEIVED';

    case OTHER = 'OTHER';
}