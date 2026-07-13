<?php

namespace App\Modules\SalesReturn\Enums;

enum SalesReturnRefundType: string
{
    case CREDIT_NOTE = 'credit_note';

    case CASH_REFUND = 'cash_refund';

    case BANK_REFUND = 'bank_refund';
}