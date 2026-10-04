<?php

namespace App\Modules\Tax\Enums;

enum TaxCalculationMethodEnum: string
{
    case PERCENTAGE = 'PERCENTAGE';
    case FIXED = 'FIXED';
}