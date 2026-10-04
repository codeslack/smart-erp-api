<?php

namespace App\Modules\Tax\Enums;

enum TaxTreatmentEnum: string
{
    case STANDARD = 'STANDARD';
    case ZERO_RATED = 'ZERO_RATED';
    case NIL_RATED = 'NIL_RATED';
    case EXEMPT = 'EXEMPT';
    case NON_TAXABLE = 'NON_TAXABLE';
}