<?php

namespace App\Modules\CustomerReceipt\Enums;

enum CustomerReceiptType: string
{
    case INVOICE = 'invoice';

    case ADVANCE = 'advance';
    
    case REFUND = 'refund';
}