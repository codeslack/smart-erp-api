<?php

namespace App\Modules\Tax\Enums;

enum TaxTypeEnum: string
{
    case GST = 'GST';
    case VAT = 'VAT';
    case SALES_TAX = 'SALES_TAX';
    case SERVICE_TAX = 'SERVICE_TAX';
    case CESS = 'CESS';
    case OTHER = 'OTHER';
}