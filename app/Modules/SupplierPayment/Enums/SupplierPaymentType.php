<?php

namespace App\Modules\SupplierPayment\Enums;

enum SupplierPaymentType: string
{
    case INVOICE = 'invoice';

    case ADVANCE = 'advance';

    case REFUND = 'refund';
}