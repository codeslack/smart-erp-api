<?php

namespace App\Modules\Tax\Enums;

enum TaxDirectionEnum: string
{
    case INPUT = 'INPUT';
    case OUTPUT = 'OUTPUT';
}