<?php

namespace App\Modules\Tax\Enums;

enum TaxPricingModeEnum: string
{
    case EXCLUSIVE = 'EXCLUSIVE';
    case INCLUSIVE = 'INCLUSIVE';
}