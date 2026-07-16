<?php

namespace App\Modules\PurchaseReturn\Enums;

enum PurchaseReturnRefundType: string
{
    case CREDIT_NOTE = 'credit_note';

    case CASH_REFUND = 'cash_refund';

    case BANK_REFUND = 'bank_refund';
}
