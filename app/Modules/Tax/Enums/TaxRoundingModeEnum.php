<?php

namespace App\Modules\Tax\Enums;

enum TaxRoundingModeEnum: string
{
    case PER_LINE = 'PER_LINE';
    case PER_COMPONENT = 'PER_COMPONENT';
    case PER_DOCUMENT = 'PER_DOCUMENT';
}