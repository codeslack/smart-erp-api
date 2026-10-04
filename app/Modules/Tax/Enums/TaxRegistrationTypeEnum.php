<?php

namespace App\Modules\Tax\Enums;

enum TaxRegistrationTypeEnum: string
{
    case GSTIN = 'GSTIN';
    case VAT = 'VAT';
    case TIN = 'TIN';
    case TAX_ID = 'TAX_ID';
    case OTHER = 'OTHER';
}