<?php

namespace App\Modules\Tax\Enums;

enum TaxUsageEnum: string
{
    case PURCHASE = 'PURCHASE';
    case SALES = 'SALES';
    case BOTH = 'BOTH';
}