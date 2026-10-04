<?php

namespace App\Modules\Tax\Enums;

enum TaxComponentTypeEnum: string
{
    case CGST = 'CGST';
    case SGST = 'SGST';
    case IGST = 'IGST';
    case UTGST = 'UTGST';
    case CESS = 'CESS';
    case OTHER = 'OTHER';
}